function getAppBasePath() {
    const pathname = window.location.pathname;

    if (pathname.includes('/frontend/')) {
        return pathname.split('/frontend/')[0];
    }

    return pathname.replace(/\/[^/]*$/, '');
}

const API_URL = `${window.location.origin}${getAppBasePath()}/backend/index.php?route=`;

async function apiRequest(route, options = {}) {
    const response = await fetch(API_URL + route, {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...(options.headers || {})
        }
    });

    return await response.json();
}

//Products
    export async function getProducts() {
        return await apiRequest("products");
    }

    export async function getProductFormula(productID, processID) {
        return await apiRequest(`products&action=formula&productID=${encodeURIComponent(productID)}&processID=${encodeURIComponent(processID)}`);
    }

    export async function createProduct(data) {
        return await apiRequest("products&action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function stockProduct(data) {
        return await apiRequest("products&action=stock", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function updateProduct(data) {
        return await apiRequest("products&action=update", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function setProductStock(data) {
        return await apiRequest("products&action=update_stock", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function archiveProduct(data) {
        return await apiRequest("products&action=archive", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

//Raw Materials
    export async function getRawMaterials() {
        return await apiRequest("raw_materials");
    }

    export async function createRawMaterial(data) {
        return await apiRequest("raw_materials&action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function stockRawMaterial(data) {
        return await apiRequest("raw_materials&action=stock", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function updateRawMaterial(data) {
        return await apiRequest("raw_materials&action=update", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function setRawMaterialStock(data) {
        return await apiRequest("raw_materials&action=update_stock", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function archiveRawMaterial(data) {
        return await apiRequest("raw_materials&action=archive", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

//Packaging
    export async function getPackaging() {
        return await apiRequest("packaging");
    }

    export async function createPackaging(data) {
        return await apiRequest("packaging&action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function stockPackaging(data) {
        return await apiRequest("packaging&action=stock", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function updatePackagingInfo(data) {
        return await apiRequest("packaging&action=update", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function setPackagingStock(data) {
        return await apiRequest("packaging&action=update_stock", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function archivePackaging(data) {
        return await apiRequest("packaging&action=archive", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

//Processes
    export async function getProcesses() {
        return await apiRequest("process");
    }

//Users
    export async function userLogin(data) {
        return await apiRequest("users&action=login", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function userRegister(data) {
        return await apiRequest("users&action=register", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
    }

    export async function getCurrentUser() {
        return await apiRequest("users&action=me");
    }

    export async function userLogout() {
        return await apiRequest("users&action=logout", {
            method: "POST"
        });
    }
