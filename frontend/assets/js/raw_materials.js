import { getRawMaterials } from "./api.js";
import { createRawMaterial } from "./api.js";
import { stockRawMaterial } from "./api.js";

export async function loadRawMaterials() {
    try {
        const response = await getRawMaterials();

        if(response.status !== "success") {
            console.error(response.message);
            return;
        }

       return response;
    } catch(error) {
        console.error("Failed to load raw materials", error);
    }
    
}

export async function addRawMaterial(data) {
    try {
        const response = await createRawMaterial(data);

        if(response.status === "success") {
            return true;
        } else {
            return false;
        }
    } catch(error) {
        console.error("Failed to add raw material", error);
    }
}

export async function stockinRawMaterial(data) {
    try {
        const response = await stockRawMaterial(data);

        if(response.status === "success") {
            return true;
        } else {
            return false;
        }
    } catch (error) {
        console.error("Failed to update raw material stock", error);
    }
}