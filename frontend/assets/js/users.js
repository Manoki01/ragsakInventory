import { userLogin } from "./api.js";
import { userRegister } from "./api.js";

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