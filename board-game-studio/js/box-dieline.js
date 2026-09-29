/**
 * Box Die-Line Generator & Design Mode Controller for Board Game Studio
 * Calculates unfolded flat box die-lines from finished 3D dimensions (Width, Length, Height),
 * manages Cut (solid) & Fold (dashed) vector overlays, and coordinates Design Mode vs. Stencil Mode.
 */
(function() {
    'use strict';

    const BOX_TYPES = {
        double_wall_tray: {
            id: 'double_wall_tray',
            name: 'Heavy-Duty Double-Wall Tray (Image Style)',
            badge: '⭐⭐⭐⭐⭐ Max Sturdiness (5/5)',
            desc: 'Double-folded side & end walls with dual corner dust flaps and shoulder locking lips (no glue required). Matches the uploaded reference box.'
        },
        roll_end_tray: {
            id: 'roll_end_tray',
            name: 'Standard Roll-End Tuck Tray',
            badge: '⭐⭐⭐⭐ High Sturdiness (4/5)',
            desc: 'Self-locking double rollover side walls with single end walls and corner dust flaps. No glue required, uses less cardboard height.'
        },
        tuck_top_box: {
            id: 'tuck_top_box',
            name: 'One-Piece Hinged Tuck-Top Box',
            badge: '⭐⭐⭐ Medium Sturdiness (3/5)',
            desc: 'All-in-one single-sheet folding box with attached hinged lid, side glue tab, dust flaps, and top/bottom tuck flaps.'
        },
        simple_tray: {
            id: 'simple_tray',
            name: 'Classic Single-Wall Tray (Corner Glue Tabs)',
            badge: '⭐⭐ Standard Sturdiness (2/5)',
            desc: 'Compact single-wall setup box tray with 4 corner glue/tape tabs. Uses the smallest flat sheet footprint.'
        }
    };

    let stencilPreviewActive = false;

    /**
     * Strict runtime schema validator for 3D Box parameters.
     */
    function validateBoxSchema(raw) {
        if (!raw || typeof raw !== 'object') {
            throw new Error('Invalid box configuration payload.');
        }
        const boxType = String(raw.boxType || 'double_wall_tray');
        if (!Object.prototype.hasOwnProperty.call(BOX_TYPES, boxType)) {
            throw new Error('Unsupported box type selected.');
        }
        const piece = (raw.piece === 'lid') ? 'lid' : 'base';
        const finishedW = Number(raw.finishedW);
        const finishedL = Number(raw.finishedL);
        const finishedH = Number(raw.finishedH);
        const clearanceMm = Number(raw.clearanceMm ?? 1.5);

        if (!Number.isFinite(finishedW) || finishedW < 15 || finishedW > 1000) {
            throw new Error('Finished Box Width (W) must be between 15 mm and 1000 mm.');
        }
        if (!Number.isFinite(finishedL) || finishedL < 15 || finishedL > 1000) {
            throw new Error('Finished Box Length (L) must be between 15 mm and 1000 mm.');
        }
        if (!Number.isFinite(finishedH) || finishedH < 8 || finishedH > 500) {
            throw new Error('Finished Box Height (H) must be between 8 mm and 500 mm.');
        }
        if (!Number.isFinite(clearanceMm) || clearanceMm < 0 || clearanceMm > 15) {
            throw new Error('Cardboard Lid Clearance must be between 0 mm and 15 mm.');
        }

        return {
            boxType,
            piece,
            finishedW: Math.round(finishedW * 10) / 10,
            finishedL: Math.round(finishedL * 10) / 10,
            finishedH: Math.round(finishedH * 10) / 10,
            clearanceMm: Math.round(clearanceMm * 10) / 10,
            showLabels: Boolean(raw.showLabels ?? true),
            fillStyle: raw.fillStyle === 'kraft' ? 'kraft' : 'stencil'
        };
    }

    function mmToPx(mm) {
        return Math.round((mm / 25.4) * 300);
    }

    function calculateFlatDimensions(cfg) {
        const isLid = (cfg.piece === 'lid' && cfg.boxType !== 'tuck_top_box');
        const W = isLid ? +(cfg.finishedW + cfg.clearanceMm * 2).toFixed(1) : cfg.finishedW;
        const L = isLid ? +(cfg.finishedL + cfg.clearanceMm * 2).toFixed(1) : cfg.finishedL;
        const H = cfg.finishedH;
        const pad = 6.0;
        let flatW = 0, flatH = 0;

        if (cfg.boxType === 'double_wall_tray') {
            const flapH = +(H * 0.75).toFixed(1);
            const shoulder = +(Math.min(4.0, Math.max(2.0, H * 0.08))).toFixed(1);
            flatW = +(W + 4 * H + 2 * shoulder + 2 * pad).toFixed(1);
            flatH = +(L + 2 * H + 2 * flapH + 2 * pad).toFixed(1);
        } else if (cfg.boxType === 'roll_end_tray') {
            const shoulder = +(Math.min(3.5, Math.max(1.5, H * 0.07))).toFixed(1);
            flatW = +(W + 4 * H + 2 * shoulder + 2 * pad).toFixed(1);
            flatH = +(L + 2 * H + 2 * pad).toFixed(1);
        } else if (cfg.boxType === 'tuck_top_box') {
            const glueTab = +(Math.min(22.0, Math.max(12.0, H * 0.45))).toFixed(1);
            const tuckFlap = +(Math.min(28.0, Math.max(12.0, H * 0.60))).toFixed(1);
            flatW = +(2 * W + 2 * H + glueTab + 2 * pad).toFixed(1);
            flatH = +(L + 2 * H + 2 * tuckFlap + 2 * pad).toFixed(1);
        } else {
            flatW = +(W + 2 * H + 2 * pad).toFixed(1);
            flatH = +(L + 2 * H + 2 * pad).toFixed(1);
        }

        return {
            effectiveW: W,
            effectiveL: L,
            effectiveH: H,
            flatWidthMm: flatW,
            flatHeightMm: flatH,
            canvasWidthPx: mmToPx(flatW),
            canvasHeightPx: mmToPx(flatH)
        };
    }

    function getActiveBoxConfig() {
        const canvas = window.editorCanvas;
        if (!canvas) return null;
        const dielineObj = canvas.getObjects().find(o => o.isBoxDieline && o.boxConfig);
        return dielineObj ? dielineObj.boxConfig : null;
    }

    /**
     * Ensures that:
     * 1) The box cardboard/white base fill ('fill') sits at the bottom of the canvas so user artwork renders above it.
     * 2) The solid Cut lines ('cut'), dashed Fold lines ('fold'), and Panel Labels ('label') sit above user artwork.
     * 3) All box die-line layers remain non-selectable/locked so user design layers are easy to manipulate.
     */
    function syncBoxDielineStack() {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        const objects = canvas.getObjects();
        const dielineObjs = objects.filter(o => o.isBoxDieline || (o.id && String(o.id).startsWith('box-dieline-')));
        if (dielineObjs.length === 0) {
            updateToolbarVisibility(false);
            return;
        }

        updateToolbarVisibility(true);

        dielineObjs.forEach(obj => {
            obj.isBoxDieline = true;
            if (!obj.dielineRole) {
                if (obj.id === 'box-dieline-fill') obj.dielineRole = 'fill';
                else if (obj.id === 'box-dieline-cut') obj.dielineRole = 'cut';
                else if (obj.id === 'box-dieline-fold') obj.dielineRole = 'fold';
                else obj.dielineRole = 'label';
            }
            obj.selectable = false;
            obj.evented = false;
            obj.lockMovementX = true;
            obj.lockMovementY = true;
            obj.lockScalingX = true;
            obj.lockScalingY = true;
            obj.lockRotation = true;
            obj.hoverCursor = 'default';
        });

        const fillObj = dielineObjs.find(o => o.dielineRole === 'fill');
        const cutObj = dielineObjs.find(o => o.dielineRole === 'cut');
        const foldObj = dielineObjs.find(o => o.dielineRole === 'fold');
        const labelObjs = dielineObjs.filter(o => o.dielineRole === 'label');

        if (fillObj) {
            fillObj.sendToBack();
        }
        if (cutObj) {
            cutObj.bringToFront();
        }
        if (foldObj) {
            foldObj.bringToFront();
        }
        labelObjs.forEach(lbl => lbl.bringToFront());

        // Keep bleed/safe guides at the absolute front
        const bleed = objects.find(o => o.id === 'bleed-zone-guide');
        const safe = objects.find(o => o.id === 'safe-zone-guide');
        if (bleed) bleed.bringToFront();
        if (safe) safe.bringToFront();
    }

    function updateToolbarVisibility(hasBoxDieline) {
        const stencilBtn = document.getElementById('btn-toggle-box-stencil');
        const boxBtn = document.getElementById('btn-open-box-dieline');
        if (stencilBtn) {
            if (hasBoxDieline) stencilBtn.classList.remove('hidden');
            else stencilBtn.classList.add('hidden');
        }
        if (boxBtn && hasBoxDieline) {
            boxBtn.classList.remove('bg-slate-900', 'text-slate-300', 'border-slate-800');
            boxBtn.classList.add('bg-amber-500/15', 'text-amber-300', 'border-amber-500/30');
        }
    }

    /**
     * Toggles between Design Mode (showing user artwork, text, and fills) and
     * Cut-Out Stencil Mode (pure white background + black solid cut lines & dashed fold lines).
     */
    function toggleStencilPreview() {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        stencilPreviewActive = !stencilPreviewActive;
        const btn = document.getElementById('btn-toggle-box-stencil');

        canvas.getObjects().forEach(obj => {
            if (obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;

            if (obj.isBoxDieline) {
                if (obj.dielineRole === 'fill') {
                    if (stencilPreviewActive) {
                        obj._savedFill = obj.fill;
                        obj.set('fill', '#ffffff');
                    } else if (obj._savedFill) {
                        obj.set('fill', obj._savedFill);
                    }
                }
            } else {
                // User Design Mode layer (artwork, text, shapes)
                if (stencilPreviewActive) {
                    obj._savedVisibleBeforeStencil = obj.visible !== false;
                    obj.set('visible', false);
                } else if (obj._savedVisibleBeforeStencil !== undefined) {
                    obj.set('visible', obj._savedVisibleBeforeStencil);
                    delete obj._savedVisibleBeforeStencil;
                }
            }
        });

        if (btn) {
            if (stencilPreviewActive) {
                btn.textContent = 'Mode: Cut-Out Stencil';
                btn.className = 'px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/25 transition shrink-0';
            } else {
                btn.textContent = 'Mode: Box Design';
                btn.className = 'px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 hover:bg-indigo-500/25 transition shrink-0';
            }
        }

        canvas.discardActiveObject();
        canvas.requestRenderAll();
    }

    function openBoxDielineModal() {
        const modal = document.getElementById('modal-box-dieline');
        if (!modal) return;

        const cfg = getActiveBoxConfig();
        if (cfg) {
            const typeEl = document.getElementById('modal-box-type');
            const partEl = document.getElementById('modal-box-part');
            const wEl = document.getElementById('modal-box-width');
            const lEl = document.getElementById('modal-box-length');
            const hEl = document.getElementById('modal-box-height');
            const cEl = document.getElementById('modal-box-clearance');
            const fillEl = document.getElementById('modal-box-fill');
            const lblEl = document.getElementById('modal-box-labels');

            if (typeEl && cfg.boxType) typeEl.value = cfg.boxType;
            if (partEl && cfg.piece) partEl.value = cfg.piece;
            if (wEl && cfg.finishedW) wEl.value = cfg.finishedW;
            if (lEl && cfg.finishedL) lEl.value = cfg.finishedL;
            if (hEl && cfg.finishedH) hEl.value = cfg.finishedH;
            if (cEl && cfg.clearanceMm !== undefined) cEl.value = cfg.clearanceMm;
            if (fillEl && cfg.fillStyle) fillEl.value = cfg.fillStyle;
            if (lblEl) lblEl.checked = cfg.showLabels !== false;
        }

        updateModalBoxPreview();
        modal.classList.remove('hidden');
    }

    function closeBoxDielineModal() {
        const modal = document.getElementById('modal-box-dieline');
        if (modal) modal.classList.add('hidden');
    }

    function updateModalBoxPreview() {
        const boxType = document.getElementById('modal-box-type')?.value || 'double_wall_tray';
        const piece = document.getElementById('modal-box-part')?.value || 'base';
        const finishedW = parseFloat(document.getElementById('modal-box-width')?.value) || 120;
        const finishedL = parseFloat(document.getElementById('modal-box-length')?.value) || 160;
        const finishedH = parseFloat(document.getElementById('modal-box-height')?.value) || 40;
        const clearanceMm = parseFloat(document.getElementById('modal-box-clearance')?.value) || 1.5;

        const meta = BOX_TYPES[boxType] || BOX_TYPES.double_wall_tray;
        const badgeEl = document.getElementById('modal-box-sturdiness-badge');
        const descEl = document.getElementById('modal-box-type-desc');
        if (badgeEl) badgeEl.textContent = meta.badge;
        if (descEl) descEl.textContent = meta.desc;

        const partGroup = document.getElementById('modal-box-part-group');
        const clearGroup = document.getElementById('modal-box-clearance-group');
        if (partGroup) partGroup.style.display = (boxType === 'tuck_top_box') ? 'none' : 'block';
        if (clearGroup) clearGroup.style.display = (boxType === 'tuck_top_box' || piece === 'base') ? 'none' : 'block';

        const dims = calculateFlatDimensions({
            boxType,
            piece,
            finishedW,
            finishedL,
            finishedH,
            clearanceMm
        });

        const flatPreview = document.getElementById('modal-box-flat-preview');
        if (flatPreview) {
            flatPreview.textContent = `${dims.flatWidthMm} × ${dims.flatHeightMm} mm (${dims.canvasWidthPx} × ${dims.canvasHeightPx} px)`;
        }
    }

    function applyBoxDielineFromModal() {
        let validated;
        try {
            validated = validateBoxSchema({
                boxType: document.getElementById('modal-box-type')?.value || 'double_wall_tray',
                piece: document.getElementById('modal-box-part')?.value || 'base',
                finishedW: parseFloat(document.getElementById('modal-box-width')?.value),
                finishedL: parseFloat(document.getElementById('modal-box-length')?.value),
                finishedH: parseFloat(document.getElementById('modal-box-height')?.value),
                clearanceMm: parseFloat(document.getElementById('modal-box-clearance')?.value ?? '1.5'),
                fillStyle: document.getElementById('modal-box-fill')?.value || 'stencil',
                showLabels: Boolean(document.getElementById('modal-box-labels')?.checked)
            });
        } catch (err) {
            if (typeof window.studioAlert === 'function') {
                window.studioAlert(err.message, 'Validation Error');
            } else {
                alert(err.message);
            }
            return;
        }

        const btn = document.getElementById('btn-confirm-box-dieline');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Generating Die-Line...';
        }

        const formData = new FormData();
        formData.append('csrf_token', window.studioConfig.csrfToken);
        formData.append('template_id', String(window.studioConfig.templateId));
        formData.append('box_type', validated.boxType);
        formData.append('box_part', validated.piece);
        formData.append('box_width_mm', String(validated.finishedW));
        formData.append('box_length_mm', String(validated.finishedL));
        formData.append('box_height_mm', String(validated.finishedH));
        formData.append('box_clearance_mm', String(validated.clearanceMm));
        formData.append('box_fill_style', validated.fillStyle);
        formData.append('box_show_labels', validated.showLabels ? '1' : '0');

        fetch('api.php?action=generate_box_dieline', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': window.studioConfig.csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Apply & Generate Unfolded Box';
            }

            if (data.error) {
                if (typeof window.studioAlert === 'function') {
                    window.studioAlert(data.error, 'Box Generator Error');
                } else {
                    alert(data.error);
                }
                return;
            }

            closeBoxDielineModal();

            const canvas = window.editorCanvas;
            if (!canvas) return;

            const oldW = window.studioConfig.canvasWidth;
            const oldH = window.studioConfig.canvasHeight;
            const newW = data.canvasWidth;
            const newH = data.canvasHeight;
            const dx = (newW - oldW) / 2;
            const dy = (newH - oldH) / 2;

            window.studioConfig.canvasWidth = newW;
            window.studioConfig.canvasHeight = newH;
            window.studioConfig.orientation = data.orientation;

            canvas.setWidth(newW);
            canvas.setHeight(newH);

            const wrapper = document.getElementById('canvas-container-wrapper');
            if (wrapper) {
                wrapper.style.width = newW + 'px';
                wrapper.style.height = newH + 'px';
            }

            // Remove old box die-line objects and shift user artwork to remain centered
            const existingObjects = canvas.getObjects().slice();
            existingObjects.forEach(obj => {
                if (obj.isBoxDieline || (obj.id && String(obj.id).startsWith('box-dieline-'))) {
                    canvas.remove(obj);
                } else if (obj.id !== 'safe-zone-guide' && obj.id !== 'bleed-zone-guide') {
                    obj.set({
                        left: (obj.left || 0) + dx,
                        top: (obj.top || 0) + dy
                    });
                    obj.setCoords();
                }
            });

            // Enliven and add the newly generated die-line objects
            const rawDielineObjects = Array.isArray(data.dielineObjects) ? data.dielineObjects : [];
            fabric.util.enlivenObjects(rawDielineObjects, (enlivened) => {
                enlivened.forEach(obj => {
                    canvas.add(obj);
                });

                syncBoxDielineStack();

                if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                    window.guideRenderer.renderGuides();
                }

                const sizeDisplay = document.getElementById('template-size-display');
                if (sizeDisplay) {
                    sizeDisplay.textContent = `${data.widthMm}x${data.heightMm} mm (${newW}x${newH} px)`;
                }

                const fitBtn = document.getElementById('btn-zoom-fit');
                if (fitBtn) fitBtn.click();

                canvas.requestRenderAll();

                if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                    window.layerManager.renderLayersList();
                }
                if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                    window.editorCore.triggerAutoSave();
                }
            });
        })
        .catch(err => {
            console.error('Box Die-Line Generation Error:', err);
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Apply & Generate Unfolded Box';
            }
            if (typeof window.studioAlert === 'function') {
                window.studioAlert('Failed to generate box die-line: ' + err.message, 'Error');
            } else {
                alert('Failed to generate box die-line: ' + err.message);
            }
        });
    }

    window.boxDieline = {
        syncBoxDielineStack,
        toggleStencilPreview,
        openBoxDielineModal,
        closeBoxDielineModal,
        updateModalBoxPreview,
        applyBoxDielineFromModal,
        getActiveBoxConfig
    };
})();
