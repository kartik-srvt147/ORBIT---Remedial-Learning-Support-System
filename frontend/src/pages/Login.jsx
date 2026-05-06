import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { useAuth } from "../context/auth.jsx";

export default function Login() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [form, setForm] = useState({ email: "", password: "" });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      await login(form);
      navigate("/", { replace: true });
    } catch (err) {
      setError(err?.message || "Login failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="authWrap">
      <motion.form
        className="authCard"
        onSubmit={onSubmit}
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="authTitle">Login</h1>
        <p className="muted">Sign in to access dashboard features.</p>

        <label className="authLabel">
          Email
          <input className="authInput" type="email" name="email" value={form.email} onChange={onChange} required />
        </label>

        <label className="authLabel">
          Password
          <input
            className="authInput"
            type="password"
            name="password"
            value={form.password}
            onChange={onChange}
            required
          />
        </label>

        {error ? <div className="authError">{error}</div> : null}

        <button className="btn btn--primary authBtn" type="submit" disabled={loading}>
          {loading ? "Signing in..." : "Login"}
        </button>
      </motion.form>
    </div>
  );
}
