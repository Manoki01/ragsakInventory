const sidebarHTML = `
<div class="flex h-screen flex-col justify-between bg-soft-black border-e border-white/5 fixed w-64 lg:w-[12.5%]">
    <div class="px-4 py-8">
        <div class="flex items-center gap-2 px-2 mb-10">
            <div class="h-6 w-6 rounded bg-moss-green flex items-center justify-center">
                <span class="text-white font-bold text-[10px]">R</span>
            </div>
            <span class="text-xs font-black uppercase tracking-widest text-white">Ragsak Farm</span>
        </div>

        <ul class="space-y-2">
            <li>
                <a href="ragsak_home.html" id="nav-home" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">
                    Home
                </a>
            </li>
            <li>
                <details class="group [&_summary::-webkit-details-marker]:hidden" id="nav-inventory-group">
                    <summary id="nav-inventory-summary" class="flex cursor-pointer items-center justify-between rounded-lg px-4 py-2 text-white/60 hover:bg-white/5 hover:text-white transition-all">
                        <span class="text-xs font-bold uppercase tracking-wider"> Inventory </span>
                        <span class="shrink-0 transition duration-300 group-open:-rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        </span>
                    </summary>
                    <ul class="mt-2 space-y-1 px-2">
                        <li><a href="ragsak_products.html" id="nav-products" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Products</a></li>
                        <li><a href="ragsak_materials.html" id="nav-materials" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Materials</a></li>
                        <li><a href="ragsak_packaging.html" id="nav-packaging" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">Packaging</a></li>
                        <li>
                            <a href="ragsak_add_stocks.html" id="nav-add-stocks" class="block rounded-md px-4 py-2 text-[10px] font-bold uppercase text-white/40 hover:text-moss-light transition-colors">
                                Add Stocks
                            </a>
                        </li>
                    </ul>
                </details>
            </li>
            <li><a href="ragsak_attendance.html" id="nav-attendance" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Attendance</a></li>
            <li><a href="ragsak_report.html" id="nav-reports" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Reports</a></li>
            <li><a href="ragsak_processes.html" id="nav-process" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Processes</a></li>
            <li><a href="ragsak_order.html" id="nav-orders" class="block rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:bg-white/5 hover:text-white transition-all">Orders</a></li>
            </ul>
    </div>

    <div class="sticky inset-x-0 bottom-0 border-t border-white/5 bg-soft-black p-4">
        <a href="../index.html" class="flex items-center gap-2 p-2 rounded-lg text-white/40 hover:text-red-500 transition-all">
            <span class="text-[10px] font-black uppercase tracking-widest">System Exit</span>
        </a>
    </div>
</div>
`;

function injectNavigation(currentPageId) {
    const container = document.getElementById('sidebar-container');
    if (container) {
        container.innerHTML = sidebarHTML;
        const activeLink = document.getElementById(currentPageId);
        
        if (activeLink) {
            // Apply ACTIVE styles to the current page link
            activeLink.classList.remove('text-white/60', 'text-white/40');
            activeLink.classList.add('bg-moss-green/20', 'border', 'border-moss-green/30', 'text-white');
            
            // LOGIC: Only open the Inventory group if we are NOT on the Home page
            if (currentPageId !== 'nav-home') {
                const detailsParent = activeLink.closest('details');
                if (detailsParent) {
                    detailsParent.setAttribute('open', '');
                    
                    // Optional: Highlight the parent "Inventory" summary text slightly 
                    // to show the category is active
                    const summary = detailsParent.querySelector('summary');
                    if (summary) summary.classList.replace('text-white/60', 'text-white');
                }
            }
        }
    }
}