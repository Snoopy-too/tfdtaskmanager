<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Right Panel: Properties Inspector -->
<div id="right-inspector-panel" class="w-[280px] shrink-0 min-w-0 bg-slate-900/50 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden transition-all duration-200">
    <div class="p-4 border-b border-slate-800">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-200">Properties Inspector</h2>
    </div>
    
    <div id="inspector-content" class="flex-grow overflow-y-auto pt-4 px-4 pb-12 space-y-4">
        <!-- Fallback notice when nothing is selected -->
        <div id="inspector-none-selected" class="space-y-5">
            <div class="text-center py-6 text-slate-500 text-xs border-b border-slate-800/80">
                <svg class="mx-auto h-8 w-8 text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                <span>Select a layer on the canvas to configure properties.</span>
            </div>

            <div class="space-y-4 pt-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Canvas Properties</h3>
                
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label for="prop-canvas-bg" class="block text-xs font-semibold text-slate-400 mb-1">Color</label>
                        <input type="color" id="prop-canvas-bg" value="#ffffff" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer p-0.5">
                    </div>
                    <div class="col-span-2">
                        <label for="prop-canvas-bg-hex" class="block text-xs font-semibold text-slate-400 mb-1">Hex Value</label>
                        <input type="text" id="prop-canvas-bg-hex" value="#ffffff" placeholder="#ffffff" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 uppercase focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex items-center space-x-2.5 pt-1">
                    <input type="checkbox" id="prop-canvas-transparent" class="h-4 w-4 bg-slate-950 border border-slate-800 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-950 rounded">
                    <label for="prop-canvas-transparent" class="text-xs font-medium text-slate-400 cursor-pointer">Transparent Canvas</label>
                </div>
            </div>
        </div>

        <!-- Form Controls (Visible when layer selected, structured dynamically in JS) -->
        <form id="inspector-form" class="space-y-4 hidden" onsubmit="return false;">
            <!-- Common Properties: Name, Position, Size, Opacity, Rotation -->
            <div class="space-y-3 pb-4 border-b border-slate-800/80">
                <div>
                    <label for="prop-name" class="block text-xs font-semibold text-slate-400 mb-1">Layer Name</label>
                    <input type="text" id="prop-name" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-left" class="block text-xs font-semibold text-slate-400 mb-1">X Pos (px)</label>
                        <input type="number" id="prop-left" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                    <div>
                        <label for="prop-top" class="block text-xs font-semibold text-slate-400 mb-1">Y Pos (px)</label>
                        <input type="number" id="prop-top" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <button type="button" id="btn-align-h" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition">
                        Center X
                    </button>
                    <button type="button" id="btn-align-v" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition">
                        Center Y
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-width" class="block text-xs font-semibold text-slate-400 mb-1">Width (px)</label>
                        <input type="number" id="prop-width" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                    <div>
                        <label for="prop-height" class="block text-xs font-semibold text-slate-400 mb-1">Height (px)</label>
                        <input type="number" id="prop-height" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-width-mm" class="block text-xs font-semibold text-slate-400 mb-1">Width (mm)</label>
                        <input type="number" id="prop-width-mm" step="0.1" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                    <div>
                        <label for="prop-height-mm" class="block text-xs font-semibold text-slate-400 mb-1">Height (mm)</label>
                        <input type="number" id="prop-height-mm" step="0.1" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-rotation" class="block text-xs font-semibold text-slate-400 mb-1">Rotation (°)</label>
                        <input type="number" id="prop-rotation" min="0" max="360" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                    <div>
                        <label for="prop-opacity" class="block text-xs font-semibold text-slate-400 mb-1">Opacity (%)</label>
                        <input type="number" id="prop-opacity" min="0" max="100" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>
            </div>

            <!-- Text-Specific Properties -->
            <div id="inspector-text-section" class="space-y-3 pb-4 border-b border-slate-800/80 hidden">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="prop-text-val" class="block text-xs font-semibold text-slate-400">Text Content</label>
                        <span id="text-bind-badge" class="text-[10px] px-1.5 py-0.5 rounded bg-violet-950/80 text-violet-300 border border-violet-800/60 hidden font-mono">Bound to Dataset</span>
                    </div>

                    <!-- Inline Formatting Toolbar -->
                    <div class="flex items-center gap-1 mb-1.5 p-1 bg-slate-900/90 border border-slate-800 rounded-lg text-xs">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider px-1">Color:</span>
                        <button type="button" class="btn-text-color-tag w-5 h-5 rounded-full bg-red-500 hover:scale-110 active:scale-95 transition-transform border border-red-400 shadow-sm" data-tag="red" title="Red (&lt;red&gt;)"></button>
                        <button type="button" class="btn-text-color-tag w-5 h-5 rounded-full bg-amber-400 hover:scale-110 active:scale-95 transition-transform border border-amber-300 shadow-sm" data-tag="gold" title="Gold (&lt;gold&gt;)"></button>
                        <button type="button" class="btn-text-color-tag w-5 h-5 rounded-full bg-blue-500 hover:scale-110 active:scale-95 transition-transform border border-blue-400 shadow-sm" data-tag="blue" title="Blue (&lt;blue&gt;)"></button>
                        <button type="button" class="btn-text-color-tag w-5 h-5 rounded-full bg-emerald-500 hover:scale-110 active:scale-95 transition-transform border border-emerald-400 shadow-sm" data-tag="green" title="Green (&lt;green&gt;)"></button>
                        <button type="button" class="btn-text-color-tag w-5 h-5 rounded-full bg-purple-500 hover:scale-110 active:scale-95 transition-transform border border-purple-400 shadow-sm" data-tag="purple" title="Purple (&lt;purple&gt;)"></button>
                        
                        <div class="relative flex items-center">
                            <input type="color" id="picker-text-custom-color" value="#f59e0b" class="opacity-0 absolute inset-0 w-5 h-5 cursor-pointer" title="Custom Hex Color">
                            <button type="button" class="w-5 h-5 rounded-full bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-center text-[10px] text-slate-300 pointer-events-none" title="Custom Color">
                                🎨
                            </button>
                        </div>

                        <div class="h-3 w-[1px] bg-slate-800 mx-0.5"></div>

                        <button type="button" id="btn-tag-bold" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-[11px] font-bold" title="Bold (&lt;b&gt;)">B</button>
                        <button type="button" id="btn-tag-italic" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-[11px] italic" title="Italic (&lt;i&gt;)">I</button>
                        <button type="button" id="btn-tag-clear" class="ml-auto px-1.5 py-0.5 rounded hover:bg-slate-800 text-slate-400 hover:text-slate-200 text-[10px]" title="Clear Tags from Selection">✕</button>
                    </div>

                    <textarea id="prop-text-val" rows="3" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 font-mono" placeholder="Type text or use tags like <gold>word</gold>..."></textarea>
                    <p class="text-[10px] text-slate-500 mt-1">Select text in the box and click a color to highlight words inline.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="prop-text-bind" class="block text-xs font-semibold text-slate-400">Dataset Variable Binding</label>
                        <span id="prop-text-bind-hint" class="text-[10px] text-amber-400/90 font-medium <?php echo $dataset ? 'hidden' : ''; ?>" title="Bind a dataset using the bottom toolbar below canvas">
                            (Select in bottom bar ▾)
                        </span>
                    </div>
                    <select id="prop-text-bind" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                        <option value="">No Binding (Static Text)</option>
                        <?php if ($dataset): ?>
                            <?php foreach ($dataset->getColumnMap() as $colName): ?>
                                <option value="{{<?php echo SecurityHelper::escape($colName); ?>}}">
                                    {{<?php echo SecurityHelper::escape($colName); ?>}}
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-font-size" class="block text-xs font-semibold text-slate-400 mb-1">Font Size (pt)</label>
                        <input type="number" id="prop-font-size" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                    <div>
                        <label for="prop-font-family" class="block text-xs font-semibold text-slate-400 mb-1">Font Family</label>
                        <select id="prop-font-family" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            <option value="Plus Jakarta Sans">Jakarta Sans (Default)</option>
                            <optgroup label="Modern Sans-Serif">
                                <option value="Inter">Inter</option>
                                <option value="Montserrat">Montserrat</option>
                                <option value="Outfit">Outfit</option>
                                <option value="Arial">Arial</option>
                            </optgroup>
                            <optgroup label="RPG & Fantasy">
                                <option value="Cinzel">Cinzel</option>
                                <option value="MedievalSharp">MedievalSharp</option>
                                <option value="Almendra">Almendra</option>
                                <option value="Rye">Rye</option>
                            </optgroup>
                            <optgroup label="Retro & Typewriter">
                                <option value="Courier Prime">Courier Prime</option>
                                <option value="Special Elite">Special Elite</option>
                            </optgroup>
                            <optgroup label="Sci-Fi & Futuristic">
                                <option value="Orbitron">Orbitron</option>
                                <option value="Rajdhani">Rajdhani</option>
                                <option value="Share Tech Mono">Share Tech Mono</option>
                                <option value="Courier New">Courier New</option>
                            </optgroup>
                            <optgroup label="Classic Serif">
                                <option value="Playfair Display">Playfair Display</option>
                                <option value="Lora">Lora</option>
                                <option value="EB Garamond">EB Garamond</option>
                                <option value="Times New Roman">Times New Roman</option>
                            </optgroup>
                            <optgroup label="Spooky & Horror">
                                <option value="Creepster">Creepster</option>
                                <option value="Metal Mania">Metal Mania</option>
                                <option value="Jolly Lodger">Jolly Lodger</option>
                            </optgroup>
                            <optgroup label="Comic & Casual">
                                <option value="Bangers">Bangers</option>
                                <option value="Fredoka">Fredoka</option>
                                <option value="Luckiest Guy">Luckiest Guy</option>
                                <option value="Comic Neue">Comic Neue</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-text-color" class="block text-xs font-semibold text-slate-400 mb-1">Font Color</label>
                        <input type="color" id="prop-text-color" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label for="prop-text-align" class="block text-xs font-semibold text-slate-400 mb-1">Align</label>
                        <select id="prop-text-align" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            <option value="left">Left</option>
                            <option value="center">Center</option>
                            <option value="right">Right</option>
                            <option value="justify">Justify</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="inline-flex items-center text-xs font-semibold text-slate-400 cursor-pointer">
                        <input type="checkbox" id="prop-font-bold" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500 mr-2">
                        Bold
                    </label>
                    <label class="inline-flex items-center text-xs font-semibold text-slate-400 cursor-pointer ml-4">
                        <input type="checkbox" id="prop-font-italic" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500 mr-2">
                        Italic
                    </label>
                </div>
            </div>

            <!-- Shape-Specific Properties (Rect, Circle) -->
            <div id="inspector-shape-section" class="space-y-3 pb-4 border-b border-slate-800/80 hidden">
                <div id="prop-shape-fill-group" class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-fill-color" class="block text-xs font-semibold text-slate-400 mb-1">Fill Color</label>
                        <input type="color" id="prop-fill-color" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer">
                    </div>
                    <div class="flex items-end pl-2">
                        <label class="inline-flex items-center text-xs font-semibold text-slate-400 cursor-pointer">
                            <input type="checkbox" id="prop-fill-transparent" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500 mr-2">
                            Transparent
                        </label>
                    </div>
                </div>

                <div id="prop-shape-opacity-group" class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-fill-opacity" class="block text-xs font-semibold text-slate-400 mb-1">Fill Opacity (%)</label>
                        <input type="number" id="prop-fill-opacity" min="0" max="100" step="5" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>

                <!-- ponytail: corner radius for rectangle layers -->
                <div id="prop-rect-corners-group" class="grid grid-cols-2 gap-3 hidden">
                    <div>
                        <label for="prop-rect-rx" class="block text-xs font-semibold text-slate-400 mb-1">Corner Radius (px)</label>
                        <input type="number" id="prop-rect-rx" min="0" max="500" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prop-stroke-color" class="block text-xs font-semibold text-slate-400 mb-1">Stroke Color</label>
                        <input type="color" id="prop-stroke-color" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label for="prop-stroke-width" class="block text-xs font-semibold text-slate-400 mb-1">Stroke Width (px)</label>
                        <input type="number" id="prop-stroke-width" min="0" max="50" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800/60">
                    <label for="prop-shape-bind" class="block text-xs font-semibold text-slate-400 mb-1">Dataset Visibility / Source Binding</label>
                    <select id="prop-shape-bind" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                        <option value="">No Binding (Always Visible)</option>
                        <?php if ($dataset): ?>
                            <?php foreach ($dataset->getColumnMap() as $colName): ?>
                                <option value="{{<?php echo SecurityHelper::escape($colName); ?>}}">
                                    {{<?php echo SecurityHelper::escape($colName); ?>}}
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Image-Specific Properties -->
            <div id="inspector-image-section" class="space-y-3 pb-4 border-b border-slate-800/80 hidden">
                <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between text-xs">
                    <span class="text-slate-400 truncate max-w-[120px]" id="prop-image-filename">No image selected</span>
                    <button type="button" id="btn-inspector-change-image" class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 rounded transition">Change</button>
                </div>

                <div>
                    <label for="prop-image-bind" class="block text-xs font-semibold text-slate-400 mb-1">Image Source Binding</label>
                    <select id="prop-image-bind" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                        <option value="">No Binding (Static Image)</option>
                        <?php if ($dataset): ?>
                            <?php foreach ($dataset->getColumnMap() as $colName): ?>
                                <option value="{{<?php echo SecurityHelper::escape($colName); ?>}}">
                                    {{<?php echo SecurityHelper::escape($colName); ?>}}
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="text-[10px] text-slate-550 mt-1">Column values should match asset filenames (e.g. fighter01.png)</p>
                </div>

                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-550 mb-1.5">Canvas Fitting</span>
                    <div class="flex space-x-2">
                        <button type="button" id="btn-inspector-fit-contain" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition flex items-center justify-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-indigo-450" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16V4m0 0h12M4 4l8 8m-8 4h16m0 0v-4m0 4l-8-8"/></svg>
                            Contain
                        </button>
                        <button type="button" id="btn-inspector-fit-cover" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition flex items-center justify-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-indigo-450" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4z"/></svg>
                            Cover
                        </button>
                    </div>
                </div>

                <div class="space-y-2 border-t border-slate-800/80 pt-3">
                    <button type="button" id="btn-crop-image" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-xs font-bold uppercase rounded-xl transition flex items-center justify-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.062 10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm0 0H21m-9.75 0H3m9.75 0V3m0 7.125V21"/></svg>
                        Crop Image
                    </button>
                </div>
            </div>
        </form>
        <!-- ponytail: crop actions live outside the form/image-section so they stay visible when the crop rect (non-image) is selected -->
        <div id="crop-actions-group" class="grid grid-cols-2 gap-2 px-4 pb-4 hidden">
            <button type="button" id="btn-crop-apply" class="py-2 bg-emerald-600 hover:bg-emerald-500 text-xs font-bold uppercase rounded-xl transition flex items-center justify-center gap-1">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                Apply Crop
            </button>
            <button type="button" id="btn-crop-cancel" class="py-2 bg-slate-800 hover:bg-slate-700 text-xs font-bold uppercase rounded-xl transition flex items-center justify-center gap-1">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel
            </button>
        </div>
    </div>
</div>
