const API_URL = "http://localhost/ragsakInventory/backend/index.php?route=";

//Products
    export async function getProducts() {
        const response = await fetch(API_URL + "products");

        return await response.json();
    }

    export async function createProduct(data) {
        const response = await fetch(API_URL + "products&action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    export async function stockProduct(data) {
        const response = await fetch(API_URL + "products&action=stock", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

//Raw Materials
    export async function getRawMaterials() {
        const response = await fetch(API_URL + "raw_materials");

        return await response.json();
    }

    export async function createRawMaterial(data) {
        const response = await fetch(API_URL + "raw_materials&action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    export async function stockRawMaterial(data) {
        const response = await fetch(API_URL + "raw_materials&action=stock", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

//Packaging
    export async function getPackaging() {
        const response = await fetch(API_URL + "packaging");

        return await response.json();
    }

    export async function createPackaging(data) {
        const response = await fetch(API_URL + "packaging&action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    export async function stockPackaging(data) {
        const response = await fetch(API_URL + "packaging&action=stock", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

//Processes
    export async function getProcesses() {
        const response = await fetch(API_URL + "process");

        return await response.json();
    }

//Users
    export async function userLogin(data) {
        const response = await fetch(API_URL + "users&action=login", {
            method: "POST",
            headers: { "Content-Type" : "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    export async function userRegister(data) {
        const response = await fetch(API_URL + "users&action=register", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        return await response.json();
    }