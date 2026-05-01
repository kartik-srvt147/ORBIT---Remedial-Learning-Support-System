import { Navigate, Outlet } from "react-router-dom";
import { useAuth } from "../context/auth.jsx";

function defaultPathForRole(role) {
  if (role === "admin") return "/admin";
  if (role === "teacher") return "/teacher";
  if (role === "student") return "/student";
  return "/profile";
}

export default function ProtectedRoute({ roles }) {
  const { isAuthenticated, user } = useAuth();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (roles?.length && !roles.includes(user?.role)) {
    return <Navigate to={defaultPathForRole(user?.role)} replace />;
  }

  return <Outlet />;
}
