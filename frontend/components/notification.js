export function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast-card ${type === 'success' ? 'toast-success' : 'toast-error'}`;
            toast.innerHTML = `
                <div class="w-2 h-2 rounded-full ${type === 'success' ? 'bg-moss-green' : 'bg-red-500'} animate-pulse"></div>
                <p class="text-[10px] font-black uppercase tracking-widest">${message}</p>
            `;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }
