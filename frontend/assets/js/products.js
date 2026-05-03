import { getProducts } from "./api.js";
import { getProductFormula } from "./api.js";
import { saveProductFormula } from "./api.js";
import { createProduct } from "./api.js";
import { stockProduct } from "./api.js";
import { updateProduct } from "./api.js";
import { setProductStock } from "./api.js";
import { archiveProduct } from "./api.js";

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

export async function loadProductFormula(productID, processID) {
    try {
        const response = await getProductFormula(productID, processID);

        return {
            success: response.status === "success",
            data: response.data || { rawMaterials: [], packaging: [] },
            message: response.message || "Formula loaded"
        };
    } catch (error) {
        console.error("Failed to load product formula", error);
        return {
            success: false,
            data: { rawMaterials: [], packaging: [] },
            message: "Network error while loading formula"
        };
    }
}

export async function editProductFormula(data) {
    try {
        const response = await saveProductFormula(data);

        return {
            success: response.status === "success",
            message: response.message || "Formula saved"
        };
    } catch (error) {
        console.error("Failed to save product formula", error);
        return {
            success: false,
            message: "Network error while saving formula"
        };
    }
}

export async function stockinProduct(data) {
    try {
        const response = await stockProduct(data);

        return {
            success: response.status === "success",
            message: response.message || "Product stock-in complete"
        };
    } catch (error) {
        console.error("Failed to update product stock", error);
        return {
            success: false,
            message: "Network error while stocking product"
        };
    }
}

export async function editProductInfo(data) {
    try {
        const response = await updateProduct(data);

        return {
            success: response.status === "success",
            message: response.message || "Product update complete"
        };
    } catch (error) {
        console.error("Failed to update product", error);
        return {
            success: false,
            message: "Network error while updating product"
        };
    }
}

export async function editProductStock(data) {
    try {
        const response = await setProductStock(data);

        return {
            success: response.status === "success",
            message: response.message || "Product stock update complete"
        };
    } catch (error) {
        console.error("Failed to update product stock", error);
        return {
            success: false,
            message: "Network error while updating product stock"
        };
    }
}

export async function deleteProduct(data) {
    try {
        const response = await archiveProduct(data);

        return {
            success: response.status === "success",
            message: response.message || "Product archived"
        };
    } catch (error) {
        console.error("Failed to archive product", error);
        return {
            success: false,
            message: "Network error while archiving product"
        };
    }
}
