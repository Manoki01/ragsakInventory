import { getProcesses } from "./api.js";
import { addProduct } from "./products.js"

async function loadProcesses() {
    try {
        const response = await getProcesses();

        if(response.status !== "success") {
            console.error(response.message);

            return false;
        }

        displayProcesses(response.data);
    } catch(error) {
        console.error("Failed to load processes", error);
    }
}

loadProcesses();

function displayProcesses(processes) {
    const dataBody = document.getElementById("processCheckboxContainer");

    dataBody.innerHTML = "";

    if(!processes || processes.length === 0) {
        dataBody.innerHTML = `<div class="text-gray-400 text-[10px] uppercase">No processes available</div>`;

        return;
    }

    processes.forEach(process => {
        const name = process.processName;

        const wrapper = document.createElement('div');
        wrapper.classList.add('relative');

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = `proc_${process.processID}`;
        checkbox.name = 'selectedProcesses';
        checkbox.value = process.processID;
        checkbox.classList.add('hidden', 'peer', 'process-checkbox');

        const label = document.createElement('label');
        label.htmlFor = `proc_${process.processID}`;
        label.className = 'flex items-center justify-between px-3 py-2 border border-gray-100 rounded-lg cursor-pointer text-[8px] font-bold uppercase transition-all hover:bg-gray-50 peer-checked:border-moss-green peer-checked:bg-moss-green/10';
        label.textContent = name;

        const checkmark = document.createElement('span');
        checkmark.className = 'text-moss-green opacity-0 peer-checked:opacity-100 font-black';
        checkmark.textContent = '✔';

        label.appendChild(checkmark);
        wrapper.append(checkbox, label);
        dataBody.appendChild(wrapper);
    });

    // Grab the form
    const intakeForm = document.getElementById("intakeForm");

    // Function to process form submission
    function processIntake() {
        // Get the target database
        const targetDb = document.getElementById("targetDb").value;

        // Get the other inputs
        const productName = document.getElementById("itemName").value.trim();
        const unitPrice = parseFloat(document.getElementById("itemPrice").value);
        const unitType = document.getElementById("itemUnit").value.trim();

        // Get selected processes (checkboxes)
        const processID = Array.from(
            document.querySelectorAll("#processCheckboxContainer input[type='checkbox']:checked")
        ).map(cb => parseInt(cb.value.replace("proc_", "")));

        // Prepare the data as JSON
        const data = {
            productName: productName,
            unitType: unitType,
            unitPrice: unitPrice,
            processes: processID
        };

        console.log("Passing data to addProduct:", data);

        // Call addProduct from products.js
        if (typeof addProduct === "function") {
            addProduct(data);
        } else {
            console.error("addProduct function not found!");
        }
    }

    // Optional: Update hidden input when radio changes
    function updateDbFromRadio(value) {
        document.getElementById("targetDb").value = value;
    }

    // Attach form submission handler
    intakeForm.addEventListener("submit", event => {
        event.preventDefault(); // Prevent default form submission
        processIntake();
    });
}