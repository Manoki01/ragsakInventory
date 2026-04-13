import { getPackaging } from "./api.js";
import { createPackaging } from "./api.js";
import { stockPackaging } from "./api.js";
import { checkStockStatus } from "./utils.js";

async function loadPackaging() {
    try {
        const response = await getPackaging();

        if(response.status !== "success") {
            console.error(response.message);
            return;
        }

       displayPackaging(response.data);
    } catch(error) {
        console.error("Failed to load packaging", error);
    }
    
}

loadPackaging();

function displayPackaging(packaging) {

    const dataBody = document.getElementById("packagingData");

    dataBody.innerHTML = "";

    if (!packaging || packaging.length === 0) {

        dataBody.innerHTML = `
        <tr>
            <td colspan="6">No packaging available</td>
        </tr>
        `;

        return;
    }

    packaging.forEach(pckg => {

        const row = document.createElement("tr");

        const quantityValue = Math.max(0, pckg.quantity);
        const stockStatus = checkStockStatus(quantityValue);
        const priceValue = Number(pckg.packagingPrice);
        const totEstPrice = quantityValue * priceValue;

        const name = document.createElement("td");
        name.textContent = pckg.packagingName ?? "N/A";

        const quantity = document.createElement("td");
        quantity.textContent = quantityValue;

        const unit = document.createElement("td");
        unit.textContent = pckg.unitType;

        const status = document.createElement("td");
        status.textContent = stockStatus;

        const price = document.createElement("td");
        price.textContent = "₱" + priceValue.toFixed(2);

        const total = document.createElement("td");
        total.textContent = "₱" + totEstPrice.toFixed(2);

        row.append(name, quantity, unit, status, price, total);

        dataBody.appendChild(row);

    });
}

export async function addPackaging(data) {
    try {
        const response = await createPackaging(data);

        if(response.status === "success") {
            alert("Packaging Added!");
            loadPackaging();
        } else {
            alert("Failed: ", response.message);
            console.error("API Error: " + response.message);
        }
    } catch(error) {
        console.error("Failed to add packaging", error);
    }
}

async function stockinPackaging(data) {
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