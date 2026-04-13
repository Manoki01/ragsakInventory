export function checkStockStatus(quantity, lowStockLimit = 50) {

    quantity = Number(quantity);

    if (isNaN(quantity) || quantity < 0) {
        return "Invalid";
    }

    if (quantity === 0) {
        return "Out of Stock";
    }

    if (quantity <= lowStockLimit) {
        return "Low Stock";
    }

    return "In Stock";
}