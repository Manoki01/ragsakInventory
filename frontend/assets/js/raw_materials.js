import { getRawMaterials } from "./api.js";
import { createRawMaterial } from "./api.js";
import { stockRawMaterial } from "./api.js";
import { checkStockStatus } from "./utils.js"

// async function loadRawMaterials() {
//     try {
//         const response = await getRawMaterials();

//         if(response.status !== "success") {
//             console.error(response.message);
//             return;
//         }

//        displayRawMaterials(response.data);
//     } catch(error) {
//         console.error("Failed to load raw materials", error);
//     }
    
// }

// loadRawMaterials();

// function displayRawMaterials(rawMaterials) {

//     const dataBody = document.getElementById("rawMaterialsData");

//     dataBody.innerHTML = "";

//     if (!rawMaterials || rawMaterials.length === 0) {

//         dataBody.innerHTML = `
//         <tr>
//             <td colspan="6">No raw materials available</td>
//         </tr>
//         `;

//         return;
//     }

//     rawMaterials.forEach(rawMaterial => {

//         const row = document.createElement("tr");

//         const quantityValue = Math.max(0, rawMaterial.quantity);
//         const stockStatus = checkStockStatus(quantityValue);
//         const priceValue = Number(rawMaterial.rawMaterialPrice);
//         const totEstPrice = quantityValue * priceValue;

//         const name = document.createElement("td");
//         name.textContent = rawMaterial.rawMaterialName ?? "N/A";

//         const quantity = document.createElement("td");
//         quantity.textContent = quantityValue;

//         const unit = document.createElement("td");
//         unit.textContent = rawMaterial.unitType;

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

export async function addRawMaterial(data) {
    try {
        const response = await createRawMaterial(data);

        if(response.status === "success") {
            alert("Raw Material Added!");
            loadRawMaterials();
        } else {
            alert("Failed: ", response.message);
            console.error("API Error: " + response.message);
        }
    } catch(error) {
        console.error("Failed to add raw material", error);
    }
}

async function stockinRawMaterial(data) {
    try {
        const response = await stockRawMaterial(data);

        if(response.status === "success") {
            alert("Stock In Successful");
            loadRawMaterials();
        } else {
            alert("Failed: ", response.message);
            console.error("API Error: " + response.message);
        }
    } catch (error) {
        console.error("Failed to update raw material stock", error);
    }
}