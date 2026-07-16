<?php
declare(strict_types=1);
?>
    </main>

    <!-- Global Custom Confirm Modal -->
    <div id="global_confirm_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="relative bg-slate-900 border border-slate-800 max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-lg font-bold text-white" id="global_confirm_title">Confirm Action</h3>
            <p class="text-sm text-slate-300" id="global_confirm_message">Are you sure you want to proceed?</p>
            
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" id="global_confirm_cancel_btn"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-lg transition duration-200">
                    Cancel
                </button>
                <button type="button" id="global_confirm_ok_btn"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Global Custom Alert Modal -->
    <div id="global_alert_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="relative bg-slate-900 border border-slate-800 max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-500/10 text-indigo-400">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white" id="global_alert_title">Alert</h3>
            <p class="text-sm text-slate-300" id="global_alert_message">Message goes here.</p>
            
            <div class="flex justify-center pt-2">
                <button type="button" id="global_alert_ok_btn"
                    class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-xs font-semibold rounded-lg shadow-md transition duration-200">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Global Custom Prompt Modal -->
    <div id="global_prompt_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="relative bg-slate-900 border border-slate-800 max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-lg font-bold text-white" id="global_prompt_title">Input Required</h3>
            <p class="text-sm text-slate-300" id="global_prompt_message">Enter details:</p>
            <input type="text" id="global_prompt_input" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500 focus:outline-none">
            
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" id="global_prompt_cancel_btn"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-lg transition duration-200">
                    Cancel
                </button>
                <button type="button" id="global_prompt_ok_btn"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentConfirmForm = null;

        function showCustomConfirm(message, formElement, buttonText = 'Delete', titleText = 'Confirm Action') {
            currentConfirmForm = formElement;
            document.getElementById('global_confirm_message').innerText = message;
            document.getElementById('global_confirm_ok_btn').innerText = buttonText;
            document.getElementById('global_confirm_title').innerText = titleText;
            
            // Adjust button color based on whether it is a delete action
            const okBtn = document.getElementById('global_confirm_ok_btn');
            if (buttonText.toLowerCase() === 'delete') {
                okBtn.className = "px-4 py-2 bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200";
            } else {
                okBtn.className = "px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200";
            }
            
            document.getElementById('global_confirm_modal').classList.remove('hidden');
            return false;
        }

        document.getElementById('global_confirm_cancel_btn').addEventListener('click', function() {
            document.getElementById('global_confirm_modal').classList.add('hidden');
            currentConfirmForm = null;
        });

        document.getElementById('global_confirm_ok_btn').addEventListener('click', function() {
            document.getElementById('global_confirm_modal').classList.add('hidden');
            if (currentConfirmForm) {
                currentConfirmForm.submit();
            }
        });

        // Promise-based Confirm helper
        window.studioConfirm = function(message, buttonText = 'Delete', titleText = 'Confirm Action') {
            return new Promise(resolve => {
                const modal = document.getElementById('global_confirm_modal');
                const cancelBtn = document.getElementById('global_confirm_cancel_btn');
                const okBtn = document.getElementById('global_confirm_ok_btn');
                
                document.getElementById('global_confirm_message').innerText = message;
                okBtn.innerText = buttonText;
                document.getElementById('global_confirm_title').innerText = titleText;
                
                if (buttonText.toLowerCase() === 'delete' || buttonText.toLowerCase() === 'remove') {
                    okBtn.className = "px-4 py-2 bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200";
                } else {
                    okBtn.className = "px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200";
                }
                
                modal.classList.remove('hidden');
                
                // Clean up previous event listeners by cloning buttons
                const newCancelBtn = cancelBtn.cloneNode(true);
                const newOkBtn = okBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                okBtn.parentNode.replaceChild(newOkBtn, okBtn);
                
                newCancelBtn.addEventListener('click', () => {
                    modal.classList.add('hidden');
                    resolve(false);
                });
                
                newOkBtn.addEventListener('click', () => {
                    modal.classList.add('hidden');
                    resolve(true);
                });
            });
        };

        // Promise-based Alert helper
        window.studioAlert = function(message, titleText = 'Alert') {
            return new Promise(resolve => {
                const modal = document.getElementById('global_alert_modal');
                const okBtn = document.getElementById('global_alert_ok_btn');
                
                document.getElementById('global_alert_message').innerText = message;
                document.getElementById('global_alert_title').innerText = titleText;
                
                modal.classList.remove('hidden');
                
                const newOkBtn = okBtn.cloneNode(true);
                okBtn.parentNode.replaceChild(newOkBtn, okBtn);
                
                newOkBtn.addEventListener('click', () => {
                    modal.classList.add('hidden');
                    resolve();
                });
            });
        };

        // Promise-based Prompt helper
        window.studioPrompt = function(message, defaultValue = '', titleText = 'Input Required') {
            return new Promise(resolve => {
                const modal = document.getElementById('global_prompt_modal');
                const cancelBtn = document.getElementById('global_prompt_cancel_btn');
                const okBtn = document.getElementById('global_prompt_ok_btn');
                const input = document.getElementById('global_prompt_input');
                
                document.getElementById('global_prompt_message').innerText = message;
                document.getElementById('global_prompt_title').innerText = titleText;
                input.value = defaultValue;
                
                modal.classList.remove('hidden');
                input.focus();
                if (defaultValue) input.select();
                
                // Clean up previous event listeners by cloning
                const newCancelBtn = cancelBtn.cloneNode(true);
                const newOkBtn = okBtn.cloneNode(true);
                const newInput = input.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                okBtn.parentNode.replaceChild(newOkBtn, okBtn);
                input.parentNode.replaceChild(newInput, input);
                
                const close = (value) => {
                    modal.classList.add('hidden');
                    resolve(value);
                };
                
                newCancelBtn.addEventListener('click', () => close(null));
                newOkBtn.addEventListener('click', () => close(newInput.value));
                
                newInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        close(newInput.value);
                    }
                    if (e.key === 'Escape') {
                        close(null);
                    }
                });
            });
        };
    </script>

    <footer class="bg-slate-900 border-t border-slate-800 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500">
            <p>&copy; <?php echo date('Y'); ?> TFD Task Manager. Designed for The Flying Dutchmen Studios.</p>
        </div>
    </footer>

</body>
</html>
