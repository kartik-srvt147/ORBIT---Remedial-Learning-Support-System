import { useEffect, useMemo, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { getDashboardSummary } from "../services/api.js";

function StatCard({ title, value, subtitle, tone = "default", delay = 0 }) {
  const toneClass = tone === "danger" ? "card--danger" : tone === "warn" ? "card--warn" : "card--default";

  return (
    <motion.div
      className={`card ${toneClass}`}
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, delay }}
      whileHover={{ y: -4, scale: 1.01 }}
    >
      <div className="card__title">{title}</div>
      <div className="card__value">{value}</div>
      {subtitle ? <div className="card__subtitle">{subtitle}</div> : null}
    </motion.div>
  );
}

export default function Dashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [summary, setSummary] = useState(null);

  const cards = useMemo(() => {
    const s = summary || {};
    return [
      {
        title: "Total Students",
        value: s.total_students ?? "—",
        subtitle: "All enrolled students",
        tone: "default",
      },
      {
        title: "Slow Learners",
        value: s.total_slow_learners ?? "—",
        subtitle: "Need extra support",
        tone: "danger",
      },
      {
        title: "Normal Students",
        value: s.total_normal_students ?? "—",
        subtitle: "Meeting expectations",
        tone: "default",
      },
      {
        title: "Low Attendance",
        value: s.low_attendance_students ?? "—",
        subtitle: "< 75% attendance",
        tone: "warn",
      },
    ];
  }, [summary]);

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const data = await getDashboardSummary();
        if (!active) return;
        setSummary(data);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load dashboard summary");
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

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Student Performance</div>
          <h1 className="h1">Dashboard</h1>
          <p className="muted">Quick overview of performance and risk indicators.</p>
        </div>

        <div className="topbar__actions">
          <button
            className="btn btn--primary"
            type="button"
            onClick={() => window.location.reload()}
          >
            Refresh
          </button>
        </div>
      </header>

      <main>
        <AnimatePresence mode="wait">
          {loading ? (
            <motion.div
              key="loading"
              className="panel"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
            >
              <div className="skeleton-grid">
                {Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} className="skeleton" />
                ))}
              </div>
            </motion.div>
          ) : error ? (
            <motion.div
              key="error"
              className="panel panel--error"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0 }}
            >
              <div className="error-title">Couldn’t load summary</div>
              <div className="error-msg">{error}</div>
              <div className="error-hint">
                Ensure Laravel is running at <code>http://127.0.0.1:8000</code>.
              </div>
            </motion.div>
          ) : (
            <motion.div
              key="content"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
            >
              <div className="grid">
                {cards.map((c, idx) => (
                  <StatCard key={c.title} {...c} delay={idx * 0.06} />
                ))}
              </div>

              <motion.div
                className="panel"
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2 }}
              >
                <div className="panel__title">Notes</div>
                <ul className="list">
                  <li>
                    <span className="dot dot--danger" /> Slow learners are identified using average marks and attendance.
                  </li>
                  <li>
                    <span className="dot dot--warn" /> Low attendance indicates students at risk (below 75%).
                  </li>
                </ul>
              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}

