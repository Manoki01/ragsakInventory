import { userLogin } from "./api.js";
import { userRegister } from "./api.js";
import { getApprovalDataset } from "./api.js";
import { updateApprovalStatus } from "./api.js";

export async function loginUser(data) {
    try {
            const response = await userLogin(data);
            return response;
        } catch(error) {
            console.error("Login Failed", error);
            return { status: "error", message: "Network error" };
        }
}

export async function registerUsers(data) {
    try {
            const response = await userRegister(data);
            return response;
        } catch(error) {
            console.error("Register Failed", error);
            return { status: "error", message: "Network error" };
        }
}

export async function loadApprovalDataset() {
    try {
            const response = await getApprovalDataset();
            return response;
        } catch(error) {
            console.error("Approval dataset failed", error);
            return { status: "error", message: "Network error" };
        }
}

export async function setApprovalStatus(data) {
    try {
            const response = await updateApprovalStatus(data);
            return response;
        } catch(error) {
            console.error("Approval update failed", error);
            return { status: "error", message: "Network error" };
        }
}
