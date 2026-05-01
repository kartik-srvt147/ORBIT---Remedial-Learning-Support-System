import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useMemo, useState } from "react";
import { getMyStudentData } from "../services/api.js";

function formatPercent(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return "-";
  return `${Number(value).toFixed(2)}%`;
}

function formatDate(value) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "-";
  return date.toLocaleDateString();
}

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

export default function StudentDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [data, setData] = useState(null);

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const payload = await getMyStudentData();
        if (!active) return;
        setData(payload);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load student dashboard");
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

  const student = data?.student;
  const assessments = data?.assessments || [];
  const recommendations = data?.recommendations || [];
  const sections = data?.sections || [];
  const subjectTeacherPairs = sections.flatMap((section) =>
    (section.subjects || []).map((item) => ({
      section,
      subject: item.subject,
      teacher: item.teacher,
    }))
  );

  const averageMarks = useMemo(() => {
    const totals = assessments.reduce(
      (acc, item) => ({
        obtained: acc.obtained + Number(item.marks_obtained || 0),
        max: acc.max + Number(item.max_marks || 0),
      }),
      { obtained: 0, max: 0 }
    );

    return totals.max > 0 ? (totals.obtained / totals.max) * 100 : 0;
  }, [assessments]);

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">My Performance</div>
          <h1 className="h1">Student Dashboard</h1>
          <p className="muted">Your assessment, attendance, and support plan.</p>
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
              <div className="error-title">Could not load student dashboard</div>
              <div className="error-msg">{error}</div>
              <div className="error-hint">
                Ensure this student account is linked to a student record.
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
                <StatCard title="Class" value={student?.class || "-"} subtitle={`Section ${student?.section || "-"}`} />
                <StatCard title="Roll Number" value={student?.roll_number ?? "-"} subtitle={student?.name} />
                <StatCard
                  title="Average Marks"
                  value={formatPercent(averageMarks)}
                  subtitle={`${assessments.length} assessments`}
                  tone={averageMarks < 40 ? "danger" : "default"}
                />
                <StatCard
                  title="Attendance"
                  value={formatPercent(data?.attendance_percentage)}
                  subtitle="Current attendance"
                  tone={Number(data?.attendance_percentage) < 75 ? "warn" : "default"}
                />
              </div>

              <div className="roleGrid">
                <section className="panel rolePanel">
                  <div className="panel__title">My Section</div>
                  <div className="miniList">
                    {sections.map((section) => (
                      <div className="miniItem" key={section.id}>
                        <div>
                          <div className="strong">Class {section.class_name} - Section {section.section_name}</div>
                          <div className="muted">{(section.subjects || []).length} subjects assigned</div>
                        </div>
                        <span className="pill">Active</span>
                      </div>
                    ))}
                    {!sections.length ? <div className="muted">No section assigned.</div> : null}
                  </div>
                </section>

                <section className="panel rolePanel">
                  <div className="panel__title">Subjects & Teachers</div>
                  <div className="miniList">
                    {subjectTeacherPairs.map((item, idx) => (
                      <div className="miniItem" key={`${item.subject?.id}-${item.teacher?.id}-${idx}`}>
                        <div>
                          <div className="strong">{item.subject?.name || "Subject"}</div>
                          <div className="muted">{item.teacher?.name || "Teacher not assigned"}</div>
                        </div>
                        <span className="pill">{item.section.class_name}-{item.section.section_name}</span>
                      </div>
                    ))}
                    {!subjectTeacherPairs.length ? <div className="muted">No subject teachers assigned.</div> : null}
                  </div>
                </section>
              </div>

              <div className="panel">
                <div className="panel__title">Performance</div>
                <div className="tableWrap">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Subject</th>
                        <th>Marks</th>
                        <th>Max Marks</th>
                        <th>Exam Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {assessments.map((item) => (
                        <tr key={item.id}>
                          <td className="strong">{item.subject?.name || "Subject"}</td>
                          <td className="mono">{item.marks_obtained}</td>
                          <td className="mono">{item.max_marks}</td>
                          <td className="mono">{formatDate(item.exam_date)}</td>
                        </tr>
                      ))}
                      {!assessments.length ? (
                        <tr>
                          <td colSpan="4" className="muted">No assessments found.</td>
                        </tr>
                      ) : null}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="panel">
                <div className="panel__title">My Recommendations</div>
                <div className="recList">
                  {recommendations.map((item) => (
                    <div key={item.id} className="recItem">
                      <div className="recItem__tags">
                        <ActionBadge actionType={item.action_type} />
                        <span className="tag tag--muted">{item.subject?.name || "General"}</span>
                      </div>
                      <div className="recItem__desc">{item.description}</div>
                    </div>
                  ))}
                  {!recommendations.length ? (
                    <div className="muted">No recommendations assigned.</div>
                  ) : null}
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}
