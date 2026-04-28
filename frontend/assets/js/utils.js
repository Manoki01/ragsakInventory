export function checkStockStatus(quantity, lowStockLimit = 50) {

    quantity = Number(quantity);

    if (isNaN(quantity) || quantity < 0) {
        return "Invalid";
    }

    if (quantity === 0) {
        return "Out of Stock";
    }

    if (quantity <= lowStockLimit) {
        return "Low Stock";
    }

    return "In Stock";
}

// Session management
let inactivityTimer;

export function startSessionManagement() {
    if (!localStorage.getItem('jwt')) return;

    resetInactivityTimer();

    // Attach to events
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetInactivityTimer, true);
    });
}

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        logout(true); // true indicates inactivity logout
    }, 10 * 60 * 1000); // 10 minutes
}

export function logout(isInactive = false) {
    if (isInactive) {
        sessionStorage.setItem('inactivityLogout', 'true');
    }
    localStorage.removeItem('jwt');
    window.location.href = '../../index.html';
}

export function isLoggedIn() {
    return !!localStorage.getItem('jwt');
}