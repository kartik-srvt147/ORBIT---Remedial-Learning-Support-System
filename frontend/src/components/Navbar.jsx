import { NavLink } from "react-router-dom";
import { motion } from "framer-motion";
import { useTheme } from "../context/theme.jsx";
import { useAuth } from "../context/auth.jsx";

function NavItem({ to, label, end }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) => (isActive ? "nav__link nav__link--active" : "nav__link")}
    >
      {label}
    </NavLink>
  );
}

export default function Navbar() {
  const { theme, toggleTheme } = useTheme();
  const { logout, user } = useAuth();

  return (
    <motion.nav
      className="nav"
      initial={{ opacity: 0, y: -8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
    >
      <div className="nav__inner">
        <div className="nav__brand">
          <span className="nav__logo" aria-hidden="true" />
          <span className="nav__title">Student Tracker</span>
        </div>

        <div className="nav__links">
          <NavItem to="/" label="Dashboard" end />
          <NavItem to="/slow-learners" label="Slow learners" />
          <NavItem to="/recommendations" label="Recommendations" />
        </div>

        <div className="nav__actions">
          {user ? <span className="nav__user">{user.name}</span> : null}
          <button className="btn btn--nav" type="button" onClick={logout}>
            Logout
          </button>
          <button className="btn btn--nav" type="button" onClick={toggleTheme} aria-label="Toggle theme">
            {theme === "dark" ? "Light" : "Dark"}
          </button>
        </div>
      </div>
    </motion.nav>
  );
}

