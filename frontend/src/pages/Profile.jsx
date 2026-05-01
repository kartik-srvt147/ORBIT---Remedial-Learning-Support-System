import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useState } from "react";
import { getCurrentUser, getMyStudentData } from "../services/api.js";
import { useAuth } from "../context/auth.jsx";

function privilegesForRole(role) {
  if (role === "admin") {
    return [
      "View full institutional analytics.",
      "Review all slow learner and attendance risk data.",
      "Access generated recommendations and intervention plans.",
      "Use profile and account information.",
    ];
  }

  if (role === "teacher") {
    return [
      "View analytics for student support.",
      "Review slow learner lists and weak subjects.",
      "Track attendance risk indicators.",
      "Use profile and account information.",
    ];
  }

  return [
    "View personal assessment results.",
    "View personal attendance percentage.",
    "View assigned recommendations only.",
    "Use profile and account information.",
  ];
}

export default function Profile() {
  const { user: storedUser } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [user, setUser] = useState(storedUser);
  const [studentData, setStudentData] = useState(null);

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const currentUser = await getCurrentUser();
        let currentStudentData = null;
        if (currentUser?.role === "student") {
          currentStudentData = await getMyStudentData();
        }
        if (!active) return;
        setUser(currentUser);
        setStudentData(currentStudentData);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load profile");
      } finally {
        if (!active) return;
        setLoading(false);
      }
    }

    load();
    return () => {
      active = false;
    };
  }, []);

  const role = user?.role || "user";

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Account</div>
          <h1 className="h1">Profile</h1>
          <p className="muted">Your identity, role, and system privileges.</p>
        </div>
      </header>

      <main>
        <AnimatePresence mode="wait">
          {loading ? (
            <motion.div key="loading" className="panel" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              <div className="skeleton-grid">
                {Array.from({ length: 2 }).map((_, i) => (
                  <div key={i} className="skeleton" />
                ))}
              </div>
            </motion.div>
          ) : error ? (
            <motion.div key="error" className="panel panel--error" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <div className="error-title">Could not load profile</div>
              <div className="error-msg">{error}</div>
            </motion.div>
          ) : (
            <motion.div key="content" className="roleGrid" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              <section className="panel rolePanel">
                <div className="profileHeader">
                  <div className="profileAvatar">{user?.name?.charAt(0)?.toUpperCase() || "U"}</div>
                  <div>
                    <div className="profileName">{user?.name || "User"}</div>
                    <div className="muted">{user?.email}</div>
                    <div className="tag profileRole">{role}</div>
                  </div>
                </div>

                {studentData?.student ? (
                  <div className="profileFacts">
                    <div>
                      <span className="muted">Class</span>
                      <strong>{studentData.student.class}</strong>
                    </div>
                    <div>
                      <span className="muted">Section</span>
                      <strong>{studentData.student.section}</strong>
                    </div>
                    <div>
                      <span className="muted">Roll</span>
                      <strong>{studentData.student.roll_number}</strong>
                    </div>
                    <div>
                      <span className="muted">Attendance</span>
                      <strong>{Number(studentData.attendance_percentage || 0).toFixed(2)}%</strong>
                    </div>
                  </div>
                ) : null}
              </section>

              <section className="panel rolePanel">
                <div className="panel__title">Privileges</div>
                <ul className="list">
                  {privilegesForRole(role).map((item, idx) => (
                    <li key={item}>
                      <span className={idx % 2 === 0 ? "dot" : "dot dot--warn"} /> {item}
                    </li>
                  ))}
                </ul>
              </section>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}
