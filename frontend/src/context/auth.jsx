import { createContext, useContext, useMemo, useState } from "react";
import api, { AUTH_TOKEN_KEY } from "../services/api.js";

const AUTH_USER_KEY = "auth.user";

const AuthContext = createContext(null);

function readStoredUser() {
  try {
    const raw = localStorage.getItem(AUTH_USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem(AUTH_TOKEN_KEY));
  const [user, setUser] = useState(() => readStoredUser());

  const isAuthenticated = Boolean(token);

  const persistAuth = (nextToken, nextUser) => {
    setToken(nextToken);
    setUser(nextUser);
    localStorage.setItem(AUTH_TOKEN_KEY, nextToken);
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(nextUser));
  };

  const clearAuth = () => {
    setToken(null);
    setUser(null);
    localStorage.removeItem(AUTH_TOKEN_KEY);
    localStorage.removeItem(AUTH_USER_KEY);
  };

  const login = async ({ email, password }) => {
    const { data } = await api.post("/api/login", { email, password });
    persistAuth(data.token, data.user);
    return data;
  };

  const register = async ({ name, email, password, password_confirmation }) => {
    const { data } = await api.post("/api/register", {
      name,
      email,
      password,
      password_confirmation,
    });
    persistAuth(data.token, data.user);
    return data;
  };

  const logout = async () => {
    try {
      await api.post("/api/logout");
    } catch {
      // token might be expired/invalid; still clear local auth state
    } finally {
      clearAuth();
    }
  };

  const value = useMemo(
    () => ({
      token,
      user,
      isAuthenticated,
      login,
      register,
      logout,
    }),
    [token, user, isAuthenticated]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
