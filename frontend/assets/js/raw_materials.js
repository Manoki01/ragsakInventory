import { getRawMaterials } from "./api.js";
import { createRawMaterial } from "./api.js";
import { stockRawMaterial } from "./api.js";
import { updateRawMaterial } from "./api.js";
import { setRawMaterialStock } from "./api.js";
import { archiveRawMaterial } from "./api.js";

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
            return {
                success: true,
                message: response.message || "Raw material added successfully"
            };
        } else {
            return {
                success: false,
                message: response.message || "Failed to add raw material"
            };
        }
    } catch(error) {
        console.error("Failed to add raw material", error);
        return {
            success: false,
            message: "Network error while adding raw material"
        };
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

export async function editRawMaterialInfo(data) {
    try {
        const response = await updateRawMaterial(data);

        return {
            success: response.status === "success",
            message: response.message || "Raw material update complete"
        };
    } catch (error) {
        console.error("Failed to update raw material", error);
        return {
            success: false,
            message: "Network error while updating raw material"
        };
    }
}

export async function editRawMaterialStock(data) {
    try {
        const response = await setRawMaterialStock(data);

        return {
            success: response.status === "success",
            message: response.message || "Raw material stock update complete"
        };
    } catch (error) {
        console.error("Failed to update raw material stock", error);
        return {
            success: false,
            message: "Network error while updating raw material stock"
        };
    }
}

export async function deleteRawMaterial(data) {
    try {
        const response = await archiveRawMaterial(data);

        return {
            success: response.status === "success",
            message: response.message || "Raw material archived"
        };
    } catch (error) {
        console.error("Failed to archive raw material", error);
        return {
            success: false,
            message: "Network error while archiving raw material"
        };
    }
}
