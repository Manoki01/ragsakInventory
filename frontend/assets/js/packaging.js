import { getPackaging } from "./api.js";
import { createPackaging } from "./api.js";
import { stockPackaging } from "./api.js";
import { updatePackagingInfo } from "./api.js";
import { setPackagingStock } from "./api.js";
import { checkStockStatus } from "./utils.js";

export async function loadPackaging() {
    try {
        const response = await getPackaging();

        if(response.status !== "success") {
            console.error(response.message);
            return;
        }

        return response;
    } catch(error) {
        console.error("Failed to load packaging", error);
    }
    
}

export async function addPackaging(data) {
    try {
        const response = await createPackaging(data);

        if(response.status === "success") {
            return {
                success: true,
                message: response.message || "Packaging added successfully"
            };
        } else {
            return {
                success: false,
                message: response.message || "Failed to add packaging"
            };
        }
    } catch(error) {
        console.error("Failed to add packaging", error);
        return {
            success: false,
            message: "Network error while adding packaging"
        };
    }
}

export async function stockinPackaging(data) {
    try {
        const response = await stockPackaging(data);

        if(response.status === "success") {
            alert("Stock In Successful");
            loadPackaging();
        } else {
            alert("Failed: ", response.message);
            console.error("API Error: " + response.message);
        }
    } catch (error) {
        console.error("Failed to update packaging stock", error);
    }
}

export async function editPackagingInfo(data) {
    try {
        const response = await updatePackagingInfo(data);

        return {
            success: response.status === "success",
            message: response.message || "Packaging update complete"
        };
    } catch (error) {
        console.error("Failed to update packaging", error);
        return {
            success: false,
            message: "Network error while updating packaging"
        };
    }
}

export async function editPackagingStock(data) {
    try {
        const response = await setPackagingStock(data);

        return {
            success: response.status === "success",
            message: response.message || "Packaging stock update complete"
        };
    } catch (error) {
        console.error("Failed to update packaging stock", error);
        return {
            success: false,
            message: "Network error while updating packaging stock"
        };
    }
}
