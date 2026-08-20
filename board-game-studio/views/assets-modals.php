<?php
declare(strict_types=1);
?>
<!-- Upload Processing Overlay Modal -->
<div id="upload-processing-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden transition-opacity duration-300">
    <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl max-w-md w-full text-center space-y-5">
        <!-- Animated Spinner -->
        <div class="relative w-16 h-16 mx-auto">
            <div class="w-16 h-16 rounded-full border-4 border-indigo-500/20 border-t-indigo-500 animate-spin"></div>
            <svg class="w-7 h-7 text-indigo-400 absolute inset-0 m-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
        </div>
        <div class="space-y-1.5">
            <h3 class="text-lg font-bold text-slate-100">Processing Upload & Unpacking Assets...</h3>
            <p class="text-xs text-slate-400 leading-relaxed">Please wait while your files or ZIP archive are being uploaded, extracted, and registered. Do not refresh or navigate away from this page.</p>
        </div>
        <div class="inline-flex items-center space-x-2 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1.5 rounded-full text-[11px] font-semibold text-indigo-400">
            <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            <span id="upload-status-text">Import in progress...</span>
        </div>
    </div>
</div>
