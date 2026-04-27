import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useMemo, useState } from "react";
import { getRecommendations } from "../services/api.js";

function ActionBadge({ actionType }) {
  const cls =
    actionType === "counseling"
      ? "tag tag--warn"
      : actionType === "extra_class"
        ? "tag tag--danger"
        : "tag";

  const label =
    actionType === "extra_class"
      ? "Extra class"
      : actionType === "practice_assignment"
        ? "Practice"
        : actionType === "counseling"
          ? "Counseling"
          : actionType;

  return <span className={cls}>{label}</span>;
}

function SubjectTag({ subjectName }) {
  if (!subjectName) return <span className="tag tag--muted">General</span>;
  return <span className="tag tag--muted">{subjectName}</span>;
}

export default function Recommendations() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [groups, setGroups] = useState([]);

  useEffect(() => {
    let active = true;
    async function load() {
      setLoading(true);
      setError(null);
      try {
        const data = await getRecommendations();
        if (!active) return;
        setGroups(data?.data || []);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load recommendations");
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

  const stats = useMemo(() => {
    const studentCount = groups.length;
    const recCount = groups.reduce((sum, g) => sum + (g?.recommendations?.length || 0), 0);
    return { studentCount, recCount };
  }, [groups]);

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Student Performance</div>
          <h1 className="h1">Recommendations</h1>
          <p className="muted">
            Suggested actions grouped by student.{" "}
            {!loading && !error ? (
              <>
                <span className="mono">{stats.studentCount}</span> students •{" "}
                <span className="mono">{stats.recCount}</span> actions
              </>
            ) : null}
          </p>
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
              <div className="error-title">Couldn’t load recommendations</div>
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
              <div className="recGrid">
                <AnimatePresence initial={false}>
                  {groups.map((group, idx) => (
                    <motion.section
                      key={group?.student?.id ?? idx}
                      className="recCard"
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0 }}
                      transition={{ duration: 0.25, delay: Math.min(idx * 0.03, 0.25) }}
                    >
                      <div className="recCard__header">
                        <div>
                          <div className="recCard__name">{group?.student?.name || "Student"}</div>
                          <div className="recCard__meta muted">
                            Class {group?.student?.class} • Sec {group?.student?.section} • Roll{" "}
                            <span className="mono">{group?.student?.roll_number}</span>
                          </div>
                        </div>
                        <div className="recCard__count">
                          <span className="pill">{group?.recommendations?.length || 0} actions</span>
                        </div>
                      </div>

                      <div className="recList">
                        {(group?.recommendations || []).map((r) => (
                          <div key={r.id} className="recItem">
                            <div className="recItem__tags">
                              <ActionBadge actionType={r.action_type} />
                              <SubjectTag subjectName={r.subject_name} />
                            </div>
                            <div className="recItem__desc">{r.description}</div>
                          </div>
                        ))}
                        {!group?.recommendations?.length ? (
                          <div className="muted">No recommendations for this student.</div>
                        ) : null}
                      </div>
                    </motion.section>
                  ))}
                </AnimatePresence>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}

