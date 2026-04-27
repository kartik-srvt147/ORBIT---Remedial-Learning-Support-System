import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useMemo, useState } from "react";
import { getSlowLearners } from "../services/api.js";

function formatPercent(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return "—";
  return `${Number(value).toFixed(2)}%`;
}

function severity({ average_marks, attendance_percentage }) {
  const avg = Number(average_marks);
  const att = Number(attendance_percentage);

  if ((Number.isFinite(avg) && avg < 30) || (Number.isFinite(att) && att < 60)) return "critical";
  if ((Number.isFinite(avg) && avg < 40) || (Number.isFinite(att) && att < 75)) return "risk";
  return "ok";
}

function WeakSubjects({ items }) {
  if (!items?.length) return <span className="muted">None</span>;
  return (
    <div className="chips">
      {items.map((s) => (
        <span key={s.subject_id} className="chip">
          {s.subject_name} ({formatPercent(s.average_percentage)})
        </span>
      ))}
    </div>
  );
}

function SeverityPill({ level }) {
  const label = level === "critical" ? "Critical" : level === "risk" ? "Needs support" : "OK";
  const cls = level === "critical" ? "pill pill--danger" : level === "risk" ? "pill pill--warn" : "pill";
  return <span className={cls}>{label}</span>;
}

export default function SlowLearners() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [items, setItems] = useState([]);

  useEffect(() => {
    let active = true;
    async function load() {
      setLoading(true);
      setError(null);
      try {
        const data = await getSlowLearners();
        if (!active) return;
        setItems(data?.slow_learners || []);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load slow learners");
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

  const enriched = useMemo(
    () =>
      (items || []).map((s) => ({
        ...s,
        _severity: severity(s),
      })),
    [items]
  );

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Student Performance</div>
          <h1 className="h1">Slow Learners</h1>
          <p className="muted">Students flagged based on marks and attendance.</p>
        </div>

        <div className="topbar__actions">
          <button className="btn btn--primary" type="button" onClick={() => window.location.reload()}>
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
              <div className="error-title">Couldn’t load slow learners</div>
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
              <div className="panel">
                <div className="panel__title">
                  Showing <span className="mono">{enriched.length}</span> students
                </div>

                <div className="tableWrap">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Student</th>
                        <th>Avg marks</th>
                        <th>Attendance</th>
                        <th>Weak subjects</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <AnimatePresence initial={false}>
                        {enriched.map((s) => (
                          <motion.tr
                            key={s.student_id}
                            className={s._severity === "critical" ? "row row--critical" : s._severity === "risk" ? "row row--risk" : "row"}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 0.25 }}
                          >
                            <td className="strong">{s.student_name}</td>
                            <td className="mono">{formatPercent(s.average_marks)}</td>
                            <td className="mono">{formatPercent(s.attendance_percentage)}</td>
                            <td>
                              <WeakSubjects items={s.weak_subjects} />
                            </td>
                            <td>
                              <SeverityPill level={s._severity} />
                            </td>
                          </motion.tr>
                        ))}
                      </AnimatePresence>
                    </tbody>
                  </table>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}

