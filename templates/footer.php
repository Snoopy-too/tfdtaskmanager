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
            <input type="text" id="global_prompt_input" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" id="global_prompt_cancel_btn"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-xl transition duration-200">
                    Cancel
                </button>
                <button type="button" id="global_prompt_ok_btn"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-sm rounded-xl shadow-lg hover:shadow-indigo-500/20 transition duration-200">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentConfirmForm = null;

        function showCustomConfirm(message, formElement, buttonText = 'Delete', titleText = 'Confirm Action') {
            currentConfirmForm = formElement;
            window.studioConfirm(message, buttonText, titleText).then(confirmed => {
                if (confirmed && currentConfirmForm) {
                    currentConfirmForm.submit();
                    currentConfirmForm = null;
                }
            });
            return false;
        }

        // Generic modal helper to eliminate node-cloning hacks
        function _openModal(modalId, setupFn) {
            return new Promise(resolve => {
                const modal = document.getElementById(modalId);
                modal.classList.remove('hidden');
                setupFn(modal, (val) => {
                    modal.classList.add('hidden');
                    resolve(val);
                });
            });
        }

        window.studioConfirm = function(message, buttonText = 'Delete', titleText = 'Confirm Action') {
            const btnStr = (typeof buttonText === 'string') ? buttonText : 'Confirm';
            const titleStr = (typeof titleText === 'string') ? titleText : 'Confirm Action';
            const isDanger = btnStr.toLowerCase() === 'delete' || btnStr.toLowerCase() === 'remove';

            return _openModal('global_confirm_modal', (modal, close) => {
                document.getElementById('global_confirm_message').innerText = message;
                document.getElementById('global_confirm_title').innerText = titleStr;
                const okBtn = document.getElementById('global_confirm_ok_btn');
                okBtn.innerText = btnStr;
                okBtn.className = isDanger
                    ? "px-4 py-2 bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200"
                    : "px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-md transition duration-200";

                document.getElementById('global_confirm_cancel_btn').onclick = () => close(false);
                okBtn.onclick = () => close(true);
            });
        };

        window.studioAlert = function(message, titleText = 'Alert') {
            return _openModal('global_alert_modal', (modal, close) => {
                document.getElementById('global_alert_message').innerText = message;
                document.getElementById('global_alert_title').innerText = titleText;
                document.getElementById('global_alert_ok_btn').onclick = () => close();
            });
        };

        window.studioPrompt = function(message, defaultValue = '', titleText = 'Input Required') {
            return _openModal('global_prompt_modal', (modal, close) => {
                const input = document.getElementById('global_prompt_input');
                document.getElementById('global_prompt_message').innerText = message;
                document.getElementById('global_prompt_title').innerText = titleText;
                input.value = defaultValue;
                input.focus();
                if (defaultValue) input.select();

                document.getElementById('global_prompt_cancel_btn').onclick = () => close(null);
                document.getElementById('global_prompt_ok_btn').onclick = () => close(input.value);
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); close(input.value); }
                    if (e.key === 'Escape') close(null);
                };
            });
        };
    </script>

    <footer class="bg-slate-900 border-t border-slate-800 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500">
            <p>&copy; <?php echo date('Y'); ?> TFD Task Manager. Designed for The Flying Dutchmen Studios.</p>
        </div>
    </footer>
    </div> <!-- Close .flex-1 min-w-0 flex flex-col (content wrapper) -->
</div> <!-- Close .flex flex-1 min-h-[calc(100vh-48px)] w-full (layout container) -->

    <!-- Sidebar Navigation & Sticky-Top Controller (Stats App Nakayoshi Edition) -->
    <script>
    (function() {
        'use strict';

        const toggleBtn = document.getElementById('tasks-sidebar-toggle');
        const closeBtn = document.getElementById('tasks-sidebar-close');
        const sidebar = document.getElementById('tasks-sidebar');
        const overlay = document.getElementById('tasks-sidebar-overlay');

        // Ensure toggle button is attached directly to body on mobile so it is immune to ancestor containing block traps
        if (toggleBtn && toggleBtn.parentElement && toggleBtn.parentElement !== document.body) {
            document.body.appendChild(toggleBtn);
        }

        // Ensure topnav is static and scrolls away on all screens (Stats app behavior)
        let tfdNav = document.getElementById('tfd-navbar') || document.querySelector('.tfd-navbar');
        function enforceStaticNavbar() {
            if (!tfdNav) {
                tfdNav = document.getElementById('tfd-navbar') || document.querySelector('.tfd-navbar');
            }
            if (tfdNav && tfdNav.style.position !== 'static') {
                tfdNav.style.setProperty('position', 'static', 'important');
                tfdNav.style.setProperty('top', 'auto', 'important');
            }
        }
        enforceStaticNavbar();

        // Dynamically track topnav bottom position so sidebar becomes sticky-top (top: 0) as topnav scrolls away
        function updateScrollOffset() {
            enforceStaticNavbar();
            let offset = 0;
            if (tfdNav) {
                const rect = tfdNav.getBoundingClientRect();
                offset = Math.max(0, Math.round(rect.bottom));
            }
            document.documentElement.style.setProperty('--sidebar-top-offset', offset + 'px');
        }
        window.addEventListener('scroll', updateScrollOffset, { passive: true });
        window.addEventListener('resize', updateScrollOffset, { passive: true });
        window.addEventListener('load', updateScrollOffset, { passive: true });
        updateScrollOffset();

        // When topnav mobile menu is opened, ensure page is scrolled to top so navbar & [X] close button are fully visible
        document.addEventListener('click', function(e) {
            const toggle = e.target && e.target.closest('#tfdMobileToggle, .tfd-mobile-toggle');
            if (toggle && window.scrollY > 0) {
                window.scrollTo(0, 0);
            }
        });

        function openSidebar() {
            if (sidebar) sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
            document.documentElement.classList.add('sidebar-open');
            document.body.classList.add('sidebar-open');
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            document.documentElement.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
        }

        if (toggleBtn) toggleBtn.addEventListener('click', function() {
            if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        // Prevent touchmove propagation on overlay so background page does not scroll
        if (overlay) {
            overlay.addEventListener('touchmove', function(e) {
                e.preventDefault();
            }, { passive: false });
        }

        // Isolate sidebar scrolling completely from the page behind it
        if (sidebar) {
            // Prevent touchmove propagation on non-scrollable header area
            const sidebarHeader = sidebar.querySelector('.border-b') || sidebar.firstElementChild;
            if (sidebarHeader) {
                sidebarHeader.addEventListener('touchmove', function(e) {
                    e.preventDefault();
                }, { passive: false });
            }

            // Desktop wheel handling: scrolling over any part of the sidebar never scrolls the page behind it
            sidebar.addEventListener('wheel', function(e) {
                const nav = sidebar.querySelector('nav');
                if (!nav) return;

                // Wheeling over non-nav parts (like header) routes scroll into the nav
                if (!nav.contains(e.target)) {
                    nav.scrollTop += e.deltaY;
                    e.preventDefault();
                    return;
                }

                // If inside nav, prevent scroll chaining to the page when reaching top or bottom boundary
                const isAtTop = nav.scrollTop <= 0;
                const isAtBottom = Math.ceil(nav.scrollTop + nav.clientHeight) >= nav.scrollHeight;
                if ((e.deltaY < 0 && isAtTop) || (e.deltaY > 0 && isAtBottom)) {
                    e.preventDefault();
                }
            }, { passive: false });

            // Mobile touch boundary handling inside sidebar nav: prevent overscroll chaining to background
            let sidebarTouchStartY = 0;
            sidebar.addEventListener('touchstart', function(e) {
                if (e.touches && e.touches.length === 1) {
                    sidebarTouchStartY = e.touches[0].clientY;
                }
            }, { passive: true });

            sidebar.addEventListener('touchmove', function(e) {
                const nav = sidebar.querySelector('nav');
                if (!nav) return;
                if (!nav.contains(e.target)) {
                    e.preventDefault();
                    return;
                }
                const touchCurrentY = e.touches[0].clientY;
                const deltaY = sidebarTouchStartY - touchCurrentY;
                const isAtTop = nav.scrollTop <= 0;
                const isAtBottom = Math.ceil(nav.scrollTop + nav.clientHeight) >= nav.scrollHeight;
                if ((deltaY < 0 && isAtTop) || (deltaY > 0 && isAtBottom)) {
                    e.preventDefault();
                }
            }, { passive: false });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar && !sidebar.classList.contains('-translate-x-full')) {
                closeSidebar();
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768 && overlay && !overlay.classList.contains('hidden')) {
                closeSidebar();
            }
        });

        // Close sidebar on mobile when navigating
        if (sidebar) {
            sidebar.querySelectorAll('nav a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 768) closeSidebar();
                });
            });
        }

        // Hide toggle on scroll down, reveal on scroll up
        let lastScrollY = window.scrollY || window.pageYOffset || 0;
        window.addEventListener('scroll', function() {
            if (!toggleBtn || (sidebar && !sidebar.classList.contains('-translate-x-full'))) return;
            const currentScrollY = Math.max(0, window.scrollY || window.pageYOffset || 0);
            if (currentScrollY <= 15) {
                toggleBtn.classList.remove('-translate-y-20', 'opacity-0', 'pointer-events-none');
                lastScrollY = currentScrollY;
                return;
            }
            const diff = currentScrollY - lastScrollY;
            if (Math.abs(diff) < 6) return;
            if (diff > 0 && currentScrollY > 60) {
                toggleBtn.classList.add('-translate-y-20', 'opacity-0', 'pointer-events-none');
            } else if (diff < 0) {
                toggleBtn.classList.remove('-translate-y-20', 'opacity-0', 'pointer-events-none');
            }
            lastScrollY = currentScrollY;
        }, { passive: true });
    })();
    </script>

    <!-- TFD Universal Navigation Script -->
    <script src="https://theflyingdutchmen.games/javascripts/tfd-navbar.js"></script>
</body>
</html>
