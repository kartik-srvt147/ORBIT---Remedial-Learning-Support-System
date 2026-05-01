import { Navigate, Route, Routes } from "react-router-dom";
import Dashboard from "./pages/Dashboard.jsx";
import SlowLearners from "./pages/SlowLearners.jsx";
import Recommendations from "./pages/Recommendations.jsx";
import StudentDashboard from "./pages/StudentDashboard.jsx";
import AdminDashboard from "./pages/AdminDashboard.jsx";
import TeacherDashboard from "./pages/TeacherDashboard.jsx";
import Profile from "./pages/Profile.jsx";
import StudentDetail from "./pages/StudentDetail.jsx";
import Navbar from "./components/Navbar.jsx";
import Login from "./pages/Login.jsx";
import Register from "./pages/Register.jsx";
import ProtectedRoute from "./components/ProtectedRoute.jsx";
import { useAuth } from "./context/auth.jsx";

function homeForRole(role) {
  if (role === "admin") return "/admin";
  if (role === "teacher") return "/teacher";
  if (role === "student") return "/student";
  return "/profile";
}

function WithNavbar({ children }) {
  return (
    <>
      <Navbar />
      {children}
    </>
  );
}

export default function App() {
  const { isAuthenticated, user } = useAuth();

  return (
    <Routes>
      <Route
        path="/login"
        element={isAuthenticated ? <Navigate to={homeForRole(user?.role)} replace /> : <Login />}
      />
      <Route
        path="/register"
        element={isAuthenticated ? <Navigate to={homeForRole(user?.role)} replace /> : <Register />}
      />

      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<Navigate to={homeForRole(user?.role)} replace />} />
        <Route
          path="/profile"
          element={
            <WithNavbar>
              <Profile />
            </WithNavbar>
          }
        />
      </Route>

      <Route element={<ProtectedRoute roles={["admin"]} />}>
        <Route
          path="/admin"
          element={
            <WithNavbar>
              <AdminDashboard />
            </WithNavbar>
          }
        />
      </Route>

      <Route element={<ProtectedRoute roles={["teacher"]} />}>
        <Route
          path="/teacher"
          element={
            <WithNavbar>
              <TeacherDashboard />
            </WithNavbar>
          }
        />
      </Route>

      <Route element={<ProtectedRoute roles={["admin", "teacher"]} />}>
        <Route
          path="/analytics"
          element={
            <WithNavbar>
              <Dashboard />
            </WithNavbar>
          }
        />
        <Route
          path="/slow-learners"
          element={
            <WithNavbar>
              <SlowLearners />
            </WithNavbar>
          }
        />
        <Route
          path="/students/:studentId"
          element={
            <WithNavbar>
              <StudentDetail />
            </WithNavbar>
          }
        />
      </Route>

      <Route element={<ProtectedRoute roles={["admin"]} />}>
        <Route path="/dashboard" element={<Navigate to="/admin" replace />} />
        <Route
          path="/recommendations"
          element={
            <WithNavbar>
              <Recommendations />
            </WithNavbar>
          }
        />
      </Route>

      <Route element={<ProtectedRoute roles={["student"]} />}>
        <Route
          path="/student"
          element={
            <WithNavbar>
              <StudentDashboard />
            </WithNavbar>
          }
        />
      </Route>

      <Route path="*" element={<Navigate to={isAuthenticated ? homeForRole(user?.role) : "/login"} replace />} />
    </Routes>
  );
}
