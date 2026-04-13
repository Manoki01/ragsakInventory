// notification.js

function notify(type, message, delay = 2000) {
    const colors = {
        success: "#16a34a",
        error: "#dc2626",
        warning: "#f59e0b",
        info: "#2563eb"
    };

    Swal.fire({
        toast: true,
        position: "center",
        title: message,
        icon: type,
        showConfirmButton: false,
        timer: delay,
        timerProgressBar: true,
        background: colors[type] || "#333",
        color: "#ffffff",
        customClass: { popup: "center-toast" },
        didOpen: (toast) => {
            toast.addEventListener("mouseenter", Swal.stopTimer);
            toast.addEventListener("mouseleave", Swal.resumeTimer);
        },
        showClass: { popup: 'swal2-slide-in' },
        hideClass: { popup: 'swal2-slide-out' }
    });
}

// helper functions
export const notifySuccess = msg => notify("success", msg);
export const notifyError = msg => notify("error", msg);
export const notifyWarning = msg => notify("warning", msg);
export const notifyInfo = msg => notify("info", msg);

export default notify;