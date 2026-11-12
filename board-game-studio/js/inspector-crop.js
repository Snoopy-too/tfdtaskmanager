/**
 * Image Crop Module for Property Inspector
 * Handles non-destructive canvas cropping, ghost backdrop, bounding box adjustment, and coordinate re-mapping.
 */
(function() {
    'use strict';

    let isCropMode = false;
    let activeCropImage = null;   // the fabric.Image being cropped
    let _cropBg = null;           // ghost of the full image (dimmed)
    let _cropBox = null;          // the draggable/resizable crop rect
    let _cropOrigState = null;    // snapshot of original cropX/Y/width/height/scaleX/scaleY

    function startImageCrop(img) {
        if (isCropMode || !img || img.type !== 'image' || !window.editorCanvas) return;

        const canvas = window.editorCanvas;
        const imgEl  = img.getElement();

        // Natural (uncropped) pixel dimensions of the source image
        const natW = (imgEl && imgEl.naturalWidth)  || img.width  || 1;
        const natH = (imgEl && imgEl.naturalHeight) || img.height || 1;

        isCropMode      = true;
        activeCropImage = img;

        // Save original state so Cancel can fully restore
        _cropOrigState = {
            cropX: img.cropX || 0,
            cropY: img.cropY || 0,
            width: img.width,
            height: img.height,
            scaleX: img.scaleX,
            scaleY: img.scaleY,
            left: img.left,
            top: img.top,
            crop_data: img.crop_data ? { ...img.crop_data } : null
        };

        // Show Apply/Cancel UI
        const btnCrop = document.getElementById('btn-crop-image');
        const cropGroup = document.getElementById('crop-actions-group');
        if (btnCrop) btnCrop.classList.add('hidden');
        if (cropGroup) cropGroup.classList.remove('hidden');

        // 1. Compute the canvas-space position of the full (uncropped) image center
        const matrix = img.calcTransformMatrix();
        const localDx = -img.width / 2 - (img.cropX || 0) + natW / 2;
        const localDy = -img.height / 2 - (img.cropY || 0) + natH / 2;
        const fullCenter = fabric.util.transformPoint(new fabric.Point(localDx, localDy), matrix);

        // 2. Ghost image (full uncropped, dimmed)
        _cropBg = new fabric.Image(imgEl, {
            left:      fullCenter.x,
            top:       fullCenter.y,
            width:     natW,
            height:    natH,
            scaleX:    img.scaleX,
            scaleY:    img.scaleY,
            angle:     img.angle,
            originX:   'center',
            originY:   'center',
            opacity:   0.35,
            selectable: false,
            evented:    false,
            id: '_crop_bg'
        });

        // 3. Crop selection rect (positioned over the currently-cropped region)
        _cropBox = new fabric.Rect({
            left:   img.left,
            top:    img.top,
            width:  img.width  * img.scaleX,
            height: img.height * img.scaleY,
            scaleX: 1,
            scaleY: 1,
            angle:  img.angle,
            originX: 'center',
            originY: 'center',
            fill:   'rgba(99,102,241,0.10)',
            stroke: '#6366f1',
            strokeWidth: 1.5,
            strokeDashArray: null,
            cornerColor: '#6366f1',
            cornerStrokeColor: '#fff',
            cornerSize: 9,
            transparentCorners: false,
            hasRotatingPoint: false,
            lockRotation: true,
            id: '_crop_box'
        });

        // Hide the actual image while we're in crop mode
        img.visible = false;
        canvas.discardActiveObject();

        canvas.add(_cropBg);
        canvas.add(_cropBox);

        // Lock all other objects
        canvas.getObjects().forEach(obj => {
            if (obj !== _cropBox && obj !== _cropBg) {
                obj._cs = obj.selectable;
                obj._ce = obj.evented;
                obj.selectable = false;
                obj.evented    = false;
            }
        });

        canvas.setActiveObject(_cropBox);
        canvas.renderAll();
    }

    function applyImageCrop() {
        if (!isCropMode || !activeCropImage || !_cropBox || !_cropBg) return;

        const canvas = window.editorCanvas;
        const img    = activeCropImage;
        const imgEl  = img.getElement();
        const natW   = (imgEl && imgEl.naturalWidth)  || _cropOrigState.width  || 1;
        const natH   = (imgEl && imgEl.naturalHeight) || _cropOrigState.height || 1;

        // Map the crop box corners back into the source image's pixel space
        const bgMatrix  = _cropBg.calcTransformMatrix();
        const invMatrix = fabric.util.invertTransform(bgMatrix);
        const coords    = _cropBox.getCoords();
        const localTL   = fabric.util.transformPoint(coords[0], invMatrix);
        const localBR   = fabric.util.transformPoint(coords[2], invMatrix);

        let cropX = localTL.x + natW / 2;
        let cropY = localTL.y + natH / 2;
        let newW  = localBR.x - localTL.x;
        let newH  = localBR.y - localTL.y;

        // Clamp to source boundaries
        cropX = Math.max(0, cropX);
        cropY = Math.max(0, cropY);
        if (cropX + newW > natW) newW = natW - cropX;
        if (cropY + newH > natH) newH = natH - cropY;
        newW = Math.max(10, newW);
        newH = Math.max(10, newH);

        const localCx = -natW / 2 + cropX + newW / 2;
        const localCy = -natH / 2 + cropY + newH / 2;
        const newCenter = fabric.util.transformPoint(new fabric.Point(localCx, localCy), bgMatrix);

        const visW = _cropBox.width  * _cropBox.scaleX;
        const visH = _cropBox.height * _cropBox.scaleY;

        // Proportional normalized crop coordinates (relative to source natural width/height)
        const isFullImage = (cropX <= 1 && cropY <= 1 && Math.abs(newW - natW) <= 2 && Math.abs(newH - natH) <= 2);
        const crop_data = isFullImage ? null : {
            relX: cropX / natW,
            relY: cropY / natH,
            relW: newW / natW,
            relH: newH / natH
        };

        img.crop_data = crop_data;

        img.set({
            cropX,
            cropY,
            width:  newW,
            height: newH,
            scaleX: visW / newW,
            scaleY: visH / newH,
            left:   newCenter.x,
            top:    newCenter.y
        });

        _exitCropMode(true);
    }

    function cancelImageCrop() {
        if (!isCropMode || !activeCropImage) return;
        activeCropImage.set(_cropOrigState);
        if (_cropOrigState) {
            activeCropImage.crop_data = _cropOrigState.crop_data || null;
        }
        _exitCropMode(false);
    }

    /**
     * Applies normalized crop (crop_data) to any Fabric image object.
     * Keeps visual rendered bounding dimensions on canvas consistent while slicing the correct proportional region.
     */
    function applyCropToFabricImage(obj, targetWidth, targetHeight) {
        if (!obj || obj.type !== 'image') return;

        const imgEl = (typeof obj.getElement === 'function') ? obj.getElement() : obj._element;
        const natW  = (imgEl && imgEl.naturalWidth)  || (obj._element && obj._element.naturalWidth)  || obj.width  || 1;
        const natH  = (imgEl && imgEl.naturalHeight) || (obj._element && obj._element.naturalHeight) || obj.height || 1;

        // Auto-infer crop_data for legacy templates that stored cropX/cropY without crop_data
        let crop = obj.crop_data;
        if (!crop) {
            const hasLegacyCrop = (obj.cropX && obj.cropX > 0) ||
                                  (obj.cropY && obj.cropY > 0) ||
                                  (obj.width && natW > 1 && obj.width < natW - 1) ||
                                  (obj.height && natH > 1 && obj.height < natH - 1);
            if (hasLegacyCrop) {
                crop = {
                    relX: (obj.cropX || 0) / natW,
                    relY: (obj.cropY || 0) / natH,
                    relW: (obj.width || natW) / natW,
                    relH: (obj.height || natH) / natH
                };
                obj.crop_data = crop;
            }
        }

        const visW = (targetWidth && targetWidth > 0)
            ? targetWidth
            : ((obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1));
        const visH = (targetHeight && targetHeight > 0)
            ? targetHeight
            : ((obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1));

        if (crop) {
            let cropX = Math.round(crop.relX * natW);
            let cropY = Math.round(crop.relY * natH);
            let cropW = Math.round(crop.relW * natW);
            let cropH = Math.round(crop.relH * natH);

            cropX = Math.max(0, Math.min(cropX, natW - 1));
            cropY = Math.max(0, Math.min(cropY, natH - 1));
            if (cropX + cropW > natW) cropW = natW - cropX;
            if (cropY + cropH > natH) cropH = natH - cropY;
            cropW = Math.max(1, cropW);
            cropH = Math.max(1, cropH);

            obj.set({
                cropX: cropX,
                cropY: cropY,
                width: cropW,
                height: cropH,
                scaleX: (visW > 0 && cropW > 0) ? (visW / cropW) : (obj.scaleX || 1),
                scaleY: (visH > 0 && cropH > 0) ? (visH / cropH) : (obj.scaleY || 1)
            });
        } else {
            obj.set({
                cropX: 0,
                cropY: 0,
                width: natW,
                height: natH,
                scaleX: (visW > 0 && natW > 0) ? (visW / natW) : (obj.scaleX || 1),
                scaleY: (visH > 0 && natH > 0) ? (visH / natH) : (obj.scaleY || 1)
            });
        }
        obj.setCoords();
    }

    /**
     * Resets the crop on an image object, returning it to full natural framing.
     */
    function resetImageCrop(img) {
        if (!img || img.type !== 'image' || !window.editorCanvas) return;
        const canvas = window.editorCanvas;
        const imgEl = img.getElement ? img.getElement() : img._element;
        const natW = (imgEl && imgEl.naturalWidth) || img.width || 1;
        const natH = (imgEl && imgEl.naturalHeight) || img.height || 1;

        const visW = (img.width || 0) * (img.scaleX !== undefined ? img.scaleX : 1);
        const visH = (img.height || 0) * (img.scaleY !== undefined ? img.scaleY : 1);

        img.crop_data = null;
        img.set({
            cropX: 0,
            cropY: 0,
            width: natW,
            height: natH,
            scaleX: (visW > 0 && natW > 0) ? (visW / natW) : 1,
            scaleY: (visH > 0 && natH > 0) ? (visH / natH) : 1
        });
        img.setCoords();
        canvas.renderAll();

        if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
            window.propertyInspector.inspect(img);
        }

        if (window.editorCore && typeof window.editorCore.saveCanvas === 'function') {
            window.editorCore.saveCanvas();
        } else if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
            window.editorCore.triggerAutoSave();
        }

        // If this image is dataset-bound, re-apply bindings so all dataset preview rows uncrop
        if (img.variable_binding && window.templateEngine && typeof window.templateEngine.applyBindings === 'function') {
            window.templateEngine.applyBindings();
        }
    }

    function _exitCropMode(shouldSave) {
        if (!isCropMode || !window.editorCanvas) return;

        const canvas = window.editorCanvas;

        const btnCrop = document.getElementById('btn-crop-image');
        const cropGroup = document.getElementById('crop-actions-group');
        if (btnCrop) btnCrop.classList.remove('hidden');
        if (cropGroup) cropGroup.classList.add('hidden');

        if (_cropBox) canvas.remove(_cropBox);
        if (_cropBg)  canvas.remove(_cropBg);
        _cropBox = null;
        _cropBg  = null;

        canvas.getObjects().forEach(obj => {
            if (obj._cs !== undefined) { obj.selectable = obj._cs; delete obj._cs; }
            if (obj._ce !== undefined) { obj.evented    = obj._ce; delete obj._ce; }
        });

        const finalizedImg = activeCropImage;

        if (finalizedImg) {
            finalizedImg.visible = true;
            canvas.setActiveObject(finalizedImg);
            if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                window.propertyInspector.inspect(finalizedImg);
            }
        }

        canvas.renderAll();

        isCropMode       = false;
        activeCropImage  = null;
        _cropOrigState   = null;

        if (shouldSave) {
            // Save immediately so crop state is stored in template database before navigation/export
            if (window.editorCore && typeof window.editorCore.saveCanvas === 'function') {
                window.editorCore.saveCanvas();
            } else if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }
        }
    }

    document.addEventListener('keydown', (e) => {
        if (!isCropMode) return;
        if (e.key === 'Enter')  { e.preventDefault(); applyImageCrop(); }
        if (e.key === 'Escape') { e.preventDefault(); cancelImageCrop(); }
    });

    window.inspectorCrop = {
        startImageCrop,
        applyImageCrop,
        cancelImageCrop,
        resetImageCrop,
        applyCropToFabricImage,
        isCropMode: () => isCropMode
    };
})();
