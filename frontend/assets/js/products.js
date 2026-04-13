import { getProducts } from "./api.js";
import { createProduct } from "./api.js";
import { stockProduct } from "./api.js";
import { checkStockStatus } from "./utils.js";

export async function loadProducts() {
    try {
        const response = await getProducts();

        if(response.status !== "success") {
            console.error(response.message);
            return;
        }

    //    displayProducts(response.data);
        return response;
    } catch(error) {
        console.error("Failed to load products", error);
    }
    
}

// function displayProducts(products) {

//     const dataBody = document.getElementById("productsData");

//     dataBody.innerHTML = "";

//     if (!products || products.length === 0) {

//         dataBody.innerHTML = `
//         <tr>
//             <td colspan="6">No products available</td>
//         </tr>
//         `;

//         return;
//     }

//     products.forEach(product => {

//         const row = document.createElement("tr");

//         const quantityValue = Math.max(0, product.quantity);
//         const stockStatus = checkStockStatus(quantityValue);
//         const priceValue = Number(product.productPrice);
//         const totEstPrice = quantityValue * priceValue;

//         const name = document.createElement("td");
//         name.textContent = product.productName ?? "N/A";

//         const quantity = document.createElement("td");
//         quantity.textContent = quantityValue;

//         const unit = document.createElement("td");
//         unit.textContent = product.unitType;

//         const status = document.createElement("td");
//         status.textContent = stockStatus;

//         const price = document.createElement("td");
//         price.textContent = "₱" + priceValue.toFixed(2);

//         const total = document.createElement("td");
//         total.textContent = "₱" + totEstPrice.toFixed(2);

//         row.append(name, quantity, unit, status, price, total);

//         dataBody.appendChild(row);
//     });
// }

export async function addProduct(data) {
    try {
        const response = await createProduct(data);

        if(response.status === "success") {
            alert("Product Added!");
        } else {
            alert("Failed: ", response.message);
            console.error("API Error: " + response.message);
        }
    } catch(error) {
        console.error("Failed to add product", error);
    }
}

export async function stockinProduct(data) {
    try {
        const response = await stockProduct(data);

        if(response.status === "success") {
            alert("Stock In Successful");
        } else {
            alert("Failed: ", response.message);
            console.error("API Error: " + response.message);
        }
    } catch (error) {
        console.error("Failed to update product stock", error);
    }
}