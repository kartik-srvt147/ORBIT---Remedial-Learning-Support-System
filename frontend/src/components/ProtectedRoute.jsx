import { Navigate, Outlet } from "react-router-dom";
import { useAuth } from "../context/auth.jsx";

export default function ProtectedRoute() {
  const { isAuthenticated } = useAuth();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}

