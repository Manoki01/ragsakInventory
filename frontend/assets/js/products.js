import { getProducts } from "./api.js";
import { createProduct } from "./api.js";
import { stockProduct } from "./api.js";

export async function loadProducts() {
    try {
        const response = await getProducts();

        if(response.status !== "success") {
            console.error(response.message);
            return;
        }

        return response;
    } catch(error) {
        console.error("Failed to load products", error);
    }
    
}

export async function addProduct(data) {
    try {
        const response = await createProduct(data);

        if(response.status === "success") {
            return {
                success: true,
                message: response.message || "Product added successfully"
            };
        } else {
            return {
                success: false,
                message: response.message || "Failed to add product"
            };
        }
    } catch(error) {
        console.error("Failed to add product", error);
        return {
            success: false,
            message: "Network error while adding product"
        };
    }
}

export async function stockinProduct(data) {
    try {
        const response = await stockProduct(data);

        if(response.status === "success") {
            return true;
        } else {
            return false;
        }
    } catch (error) {
        console.error("Failed to update product stock", error);
    }
}
