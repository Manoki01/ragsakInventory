import { userLogin } from "./api.js";
import { userRegister } from "./api.js";

export async function loginUser(data) {
    try {
            const response = await userLogin(data);
    
            if(response.status === "success") {
                return true;
            } else {
                alert("Failed: " + response.message);
                console.error("API Error: " + response.message);
            }
        } catch(error) {
            console.error("Login Failed", error);
        }
}

export async function registerUsers(data) {
    try {
            const response = await userRegister(data);
            console.log(response.json);
    
            if(response.status === "success") {
                window.location.href = '..pages/ragsak_home.html'
            } else {    
                alert("Failed: " + response.message);
                console.error("API Error: " + response.message);
            }
        } catch(error) {
            console.error("Register Failed", error);
        }
}