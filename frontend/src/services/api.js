import axios from "axios";

export const AUTH_TOKEN_KEY = "auth.token";

const api = axios.create({
  baseURL: "http://localhost:8000",
  headers: {
    Accept: "application/json",
  },
  timeout: 15000,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  if (token) {
    config.headers = config.headers ?? {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

const normalizeAxiosError = (error) => {
  if (error?.response) {
    return {
      message: error.response.data?.message || "Request failed",
      status: error.response.status,
      data: error.response.data,
    };
  }

  if (error?.request) {
    return {
      message: "No response from server. Is the backend running?",
      status: null,
      data: null,
    };
  }

  return {
    message: error?.message || "Unexpected error",
    status: null,
    data: null,
  };
};

const get = async (url, config) => {
  try {
    const res = await api.get(url, config);
    return res.data;
  } catch (err) {
    throw normalizeAxiosError(err);
  }
};

// Dashboard APIs
export const getDashboardSummary = () => get("/api/dashboard/summary");
export const getSlowLearners = () => get("/api/dashboard/slow-learners");
export const getRecommendations = () => get("/api/dashboard/recommendations");

export default api;
