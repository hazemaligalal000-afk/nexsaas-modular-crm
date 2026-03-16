import axios from 'axios';

// Nexa CRM Core API Endpoint
const API_BASE_URL = 'http://localhost:8080/api';

/**
 * Nexa Intelligence™ API Client
 * Automatically handles Tenant Authentication and X-API-Key injection.
 */
const apiClient = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        'Content-Type': 'application/json'
    }
});

// Interceptor to inject the active Tenant/Auth Key on every request
apiClient.interceptors.request.use((config) => {
    // Retrieve the Nexa-specific API key from persistent storage
    const apiKey = localStorage.getItem('nexa_api_key');
    if (apiKey) {
        config.headers['X-API-Key'] = apiKey;
    }
    return config;
}, (error) => {
    return Promise.reject(error);
});

// Response interceptor to handle global errors (e.g., Auth Token expired)
apiClient.interceptors.response.use(
    (response) => response.data, 
    (error) => {
        if (error.response && error.response.status === 401) {
            // If the key is revoked or trial expired, redirect to public landing/login
            console.error("Nexa Intelligence™ Access Revoked. Redirecting...");
            localStorage.removeItem('nexa_api_key');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default apiClient;
