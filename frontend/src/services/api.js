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

const post = async (url, payload, config) => {
  try {
    const res = await api.post(url, payload, config);
    return res.data;
  } catch (err) {
    throw normalizeAxiosError(err);
  }
};

const put = async (url, payload, config) => {
  try {
    const res = await api.put(url, payload, config);
    return res.data;
  } catch (err) {
    throw normalizeAxiosError(err);
  }
};

const destroy = async (url, config) => {
  try {
    const res = await api.delete(url, config);
    return res.data;
  } catch (err) {
    throw normalizeAxiosError(err);
  }
};

// Dashboard APIs
export const getCurrentUser = () => get("/api/user");
export const getDashboardSummary = (params) => get("/api/dashboard/summary", { params });
export const getStudents = (params) => get("/api/dashboard/students", { params });
export const getStudentDetails = async (studentId) => {
  const user = JSON.parse(localStorage.getItem("auth.user") || "null");
  if (user?.role === "teacher") {
    return get(`/api/teacher/students/${studentId}/performance`);
  }
  return get(`/api/dashboard/students/${studentId}`);
};
export const getSlowLearners = (params) => get("/api/dashboard/slow-learners", { params });
export const getRecommendations = (params) => get("/api/dashboard/recommendations", { params });
export const getAnalytics = (params) => get("/api/dashboard/analytics", { params });
export const getStudentPerformanceTrends = (studentId, params) =>
  get(`/api/dashboard/students/${studentId}/performance-trends`, { params });
export const getNotifications = (params) => get("/api/notifications", { params });
export const getMyStudentData = () => get("/api/student/me");
export const changePassword = (payload) => post("/api/change-password", payload);

// Admin APIs
export const getAdminOverview = () => get("/api/admin/overview");
export const getAdminTeachers = (params) => get("/api/admin/teachers", { params });
export const createTeacher = (payload) => post("/api/admin/teachers", payload);
export const updateTeacher = (id, payload) => put(`/api/admin/teachers/${id}`, payload);
export const deleteTeacher = (id) => destroy(`/api/admin/teachers/${id}`);
export const getAdminStudents = (params) => get("/api/admin/students", { params });
export const createStudent = (payload) => post("/api/admin/students", payload);
export const updateStudent = (id, payload) => put(`/api/admin/students/${id}`, payload);
export const deleteStudent = (id) => destroy(`/api/admin/students/${id}`);
export const getAdminSubjects = (params) => get("/api/admin/subjects", { params });
export const createSubject = (payload) => post("/api/admin/subjects", payload);
export const updateSubject = (id, payload) => put(`/api/admin/subjects/${id}`, payload);
export const deleteSubject = (id) => destroy(`/api/admin/subjects/${id}`);
export const getAdminSections = (params) => get("/api/admin/sections", { params });
export const createSection = (payload) => post("/api/admin/sections", payload);
export const getAdminSection = (id) => get(`/api/admin/sections/${id}`);
export const updateSection = (id, payload) => put(`/api/admin/sections/${id}`, payload);
export const deleteSection = (id) => destroy(`/api/admin/sections/${id}`);
export const assignTeacherToSubjectSection = (payload) => post("/api/admin/teacher-subjects", payload);
export const assignStudentToSection = (payload) => post("/api/admin/student-sections", payload);

// Teacher APIs
export const getTeacherSections = () => get("/api/teacher/sections");
export const getTeacherStudents = () => get("/api/teacher/students");
export const getTeacherSubjectPerformance = () => get("/api/teacher/subject-performance");
export const getTeacherStudentPerformance = (studentId) => get(`/api/teacher/students/${studentId}/performance`);
export const addTeacherRemedialAction = (studentId, payload) =>
  post(`/api/teacher/students/${studentId}/remedial-actions`, payload);

export default api;
