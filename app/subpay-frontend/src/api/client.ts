import axios from 'axios';

export const apiClient = axios.create({
  // Fallback to local dev server mapped from our Docker compose steps
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor to attach Sanctum bearer tokens automatically
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('subpay_token');
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});