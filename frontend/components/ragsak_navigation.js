import { startSessionManagement, isLoggedIn, logout } from '../assets/js/utils.js';

const sidebarHTML = `
<div class="flex h-full flex-col justify-between bg-soft-black border-e border-white/5 w-72 max-w-[85vw] lg:w-64 lg:max-w-none">
    <div class="px-4 py-8">
        <div class="flex items-center gap-2 px-2 mb-10">
            <div class="h-6 w-6 rounded bg-moss-green flex items-center justify-center">
                <span class="text-white font-bold text-[10px]">R</span>
            </div>
            <span class="text-xs font-black uppercase tracking-widest text-white">Ragsak Farm</span>
        </div>

        <ul class="space-y-2">
            <li>
                <a href="ragsak_home.html" data-nav-id="nav-home" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">
                    Home
                </a>
            </li>

            <li>
                <details class="group [&_summary::-webkit-details-marker]:hidden" data-nav-group="inventory">
                    <summary data-nav-summary="inventory" class="flex cursor-pointer items-center justify-between rounded-lg px-4 py-2 text-white/60 hover:bg-white/5 hover:text-white transition-all">
                        <span class="text-xs font-bold uppercase tracking-wider">Inventory</span>
                        <span class="shrink-0 transition duration-300 group-open:-rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </summary>

                    <ul class="mt-2 space-y-1 px-2">
                        <li><a href="ragsak_products.html" data-nav-id="nav-products" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Products</a></li>
                        <li><a href="ragsak_materials.html" data-nav-id="nav-materials" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Materials</a></li>
                        <li><a href="ragsak_packaging.html" data-nav-id="nav-packaging" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Packaging</a></li>
                        <li><a href="ragsak_add_entry.html" data-nav-id="nav-add-stocks" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Add Entry</a></li>
                    </ul>
                </details>
            </li>

            <li><a href="ragsak_attendance.html" data-nav-id="nav-attendance" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Attendance</a></li>
            <li><a href="ragsak_report.html" data-nav-id="nav-reports" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Reports</a></li>
            <li><a href="ragsak_processes.html" data-nav-id="nav-process" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Processes</a></li>
            <li><a href="ragsak_order.html" data-nav-id="nav-orders" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Orders</a></li>
            <li><a href="approval.html" data-nav-id="nav-approval" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Approval</a></li>
        </ul>
    </div>

    <div class="sticky inset-x-0 bottom-0 border-t border-white/5 bg-soft-black p-4">
        <a href="#" onclick="logout()" class="flex items-center gap-2 p-2 rounded-lg text-white/40 hover:text-red-500 transition-all">
            <span class="text-[10px] font-black uppercase tracking-widest">System Exit</span>
        </a>
    </div>
</div>
`;

function closeMobileNav() {
    const drawer = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('mobile-sidebar-overlay');

    if (drawer) drawer.classList.add('-translate-x-full');
    if (overlay) overlay.classList.add('hidden');

    document.body.classList.remove('overflow-hidden');
}

function openMobileNav() {
    const drawer = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('mobile-sidebar-overlay');

    if (overlay) overlay.classList.remove('hidden');

    requestAnimationFrame(() => {
        if (drawer) drawer.classList.remove('-translate-x-full');
    });

    document.body.classList.add('overflow-hidden');
}

function applyActiveNavState(currentPageId) {
    const activeLinks = document.querySelectorAll(`[data-nav-id="${currentPageId}"]`);

    activeLinks.forEach(activeLink => {
        activeLink.classList.remove('text-white/60', 'text-white/40');
        activeLink.classList.add('bg-moss-green/20', 'border', 'border-moss-green/30', 'text-white');

        if (currentPageId !== 'nav-home') {
            const detailsParent = activeLink.closest('details');
            if (detailsParent) {
                detailsParent.setAttribute('open', '');

                const summary = detailsParent.querySelector('[data-nav-summary="inventory"]');
                if (summary) {
                    summary.classList.remove('text-white/60');
                    summary.classList.add('text-white', 'bg-white/5');
                }
            }
        }
    });
}

function injectNavigation(currentPageId) {
    const container = document.getElementById('sidebar-container');
    if (!container) return;

    container.innerHTML = `
        <aside class="hidden lg:flex lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:z-40">
            ${sidebarHTML}
        </aside>

        <header class="sticky top-0 z-40 flex items-center justify-between bg-off-white/95 backdrop-blur px-4 py-4 border-b border-black/5 lg:hidden">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded bg-moss-green flex items-center justify-center">
                    <span class="text-white font-bold text-xs">R</span>
                </div>
                <span class="text-[11px] font-black uppercase tracking-[0.2em] text-soft-black">Ragsak Farm</span>
            </div>

            <button
                type="button"
                id="mobile-nav-toggle"
                aria-label="Open navigation"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-black/10 bg-white shadow-sm"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-soft-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </header>

        <div id="mobile-sidebar-overlay" class="fixed inset-0 z-50 hidden bg-black/50 lg:hidden" onclick="closeMobileNav()"></div>

        <aside
            id="mobile-sidebar"
            class="fixed inset-y-0 left-0 z-[60] w-72 max-w-[85vw] -translate-x-full transition-transform duration-300 ease-out lg:hidden"
        >
            <div class="relative h-full">
                <button
                    type="button"
                    aria-label="Close navigation"
                    onclick="closeMobileNav()"
                    class="absolute right-3 top-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                ${sidebarHTML}
            </div>
        </aside>
    `;

    applyActiveNavState(currentPageId);

    const mobileToggle = document.getElementById('mobile-nav-toggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', openMobileNav);
    }

    container.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) closeMobileNav();
        });
    });

}

async function initNavigation() {
    if (!window.currentPageId) {
        return;
    }

    injectNavigation(window.currentPageId);

    if (!(await isLoggedIn())) {
        window.location.href = '../../index.html';
        return;
    }

    startSessionManagement();
}

if (window.currentPageId) {
    initNavigation();
}

// Make logout global for onclick
window.logout = logout;
