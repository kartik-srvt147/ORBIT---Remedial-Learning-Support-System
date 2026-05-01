import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { useAuth } from "../context/auth.jsx";

export default function Register() {
  const navigate = useNavigate();
  const { register } = useAuth();
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
  });
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
      await register(form);
      navigate("/", { replace: true });
    } catch (err) {
      setError(err?.message || "Registration failed");
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
        <h1 className="authTitle">Register</h1>
        <p className="muted">Create your account to continue.</p>

        <label className="authLabel">
          Name
          <input className="authInput" type="text" name="name" value={form.name} onChange={onChange} required />
        </label>

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

        <label className="authLabel">
          Confirm Password
          <input
            className="authInput"
            type="password"
            name="password_confirmation"
            value={form.password_confirmation}
            onChange={onChange}
            required
          />
        </label>

        {error ? <div className="authError">{error}</div> : null}

        <button className="btn btn--primary authBtn" type="submit" disabled={loading}>
          {loading ? "Creating..." : "Register"}
        </button>

        <p className="muted authFoot">
          Already have an account? <Link to="/login">Login</Link>
        </p>
      </motion.form>
    </div>
  );
}
