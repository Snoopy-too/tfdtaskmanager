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
    </script>

    <footer class="bg-slate-900 border-t border-slate-800 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500">
            <p>&copy; <?php echo date('Y'); ?> TFD Task Manager. Designed for Board Game Development Teams.</p>
        </div>
    </footer>

</body>
</html>
