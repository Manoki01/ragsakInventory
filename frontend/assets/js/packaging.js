import { getPackaging } from "./api.js";
import { createPackaging } from "./api.js";
import { stockPackaging } from "./api.js";
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
            return true;
        } else {
            return false;
        }
    } catch(error) {
        console.error("Failed to add packaging", error);
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