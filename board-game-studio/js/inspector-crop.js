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
            top: img.top
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
        _exitCropMode(false);
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

        if (activeCropImage) {
            activeCropImage.visible = true;
            canvas.setActiveObject(activeCropImage);
            if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                window.propertyInspector.inspect(activeCropImage);
            }
        }

        canvas.renderAll();

        isCropMode       = false;
        activeCropImage  = null;
        _cropOrigState   = null;

        if (shouldSave && window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
            window.editorCore.triggerAutoSave();
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
        isCropMode: () => isCropMode
    };
})();
