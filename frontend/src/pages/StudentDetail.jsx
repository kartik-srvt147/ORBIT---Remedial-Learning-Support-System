import { AnimatePresence, motion } from "framer-motion";
import { Link, useParams } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import {
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { getStudentDetails, getStudentPerformanceTrends } from "../services/api.js";

function formatPercent(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return "-";
  return `${Number(value).toFixed(2)}%`;
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

export default function StudentDetail() {
  const { studentId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [detail, setDetail] = useState(null);
  const [trend, setTrend] = useState([]);

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const [detailData, trendData] = await Promise.all([
          getStudentDetails(studentId),
          getStudentPerformanceTrends(studentId),
        ]);
        if (!active) return;
        setDetail(detailData);
        setTrend(trendData?.data || []);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load student details");
      } finally {
        if (!active) return;
        setLoading(false);
      }
    }

    load();
    return () => {
      active = false;
    };
  }, [studentId]);

  const weakSubjects = detail?.weak_subjects || [];
  const recommendations = detail?.recommendations || [];
  const assessmentCount = detail?.assessments?.length || 0;

  const trendTone = useMemo(() => {
    const last = trend[trend.length - 1];
    const percentage = Number(last?.percentage);
    if (!Number.isFinite(percentage)) return "default";
    if (percentage < 40) return "danger";
    if (percentage < 55) return "warn";
    return "default";
  }, [trend]);

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Student Detail</div>
          <h1 className="h1">{detail?.student?.name || "Student"}</h1>
          <p className="muted">
            Class {detail?.student?.class || "-"} - Section {detail?.student?.section || "-"} - Roll {detail?.student?.roll_number || "-"}
          </p>
        </div>

        <div className="topbar__actions">
          <Link className="btn" to="/analytics">Back to analytics</Link>
        </div>
      </header>

      <main>
        <AnimatePresence mode="wait">
          {loading ? (
            <motion.div key="loading" className="panel" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              <div className="skeleton-grid">
                {Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} className="skeleton" />
                ))}
              </div>
            </motion.div>
          ) : error ? (
            <motion.div key="error" className="panel panel--error" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <div className="error-title">Could not load student</div>
              <div className="error-msg">{error}</div>
            </motion.div>
          ) : (
            <motion.div key="content" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              <div className="studentHero">
                <div className="studentHero__avatar">{detail?.student?.name?.charAt(0)?.toUpperCase() || "S"}</div>
                <div>
                  <div className="studentHero__name">{detail?.student?.name}</div>
                  <div className="muted">
                    Teacher: {detail?.student?.teacher?.name || "Not assigned"}
                  </div>
                </div>
                <span className={weakSubjects.length ? "pill pill--danger" : "pill"}>{weakSubjects.length ? "Needs support" : "On track"}</span>
              </div>

              <div className="grid">
                <StatCard title="Average Marks" value={formatPercent(detail?.average_marks)} subtitle={`${assessmentCount} assessments`} tone={trendTone} />
                <StatCard
                  title="Attendance"
                  value={formatPercent(detail?.attendance_percentage)}
                  subtitle="Current attendance"
                  tone={Number(detail?.attendance_percentage) < 75 ? "warn" : "default"}
                />
                <StatCard title="Weak Subjects" value={weakSubjects.length} subtitle="Below 40%" tone={weakSubjects.length ? "danger" : "default"} />
                <StatCard title="Recommendations" value={recommendations.length} subtitle="Active actions" />
              </div>

              <section className="chartPanel chartPanel--wide">
                <div className="chartPanel__head">
                  <div className="panel__title">Performance Trend</div>
                  <span className="pill">{trend.length} points</span>
                </div>
                <div className="chartBox chartBox--tall">
                  {trend.length ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <LineChart data={trend}>
                        <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.10)" />
                        <XAxis dataKey="date" tick={{ fill: "currentColor", fontSize: 12 }} />
                        <YAxis tick={{ fill: "currentColor", fontSize: 12 }} domain={[0, 100]} />
                        <Tooltip />
                        <Line type="monotone" dataKey="percentage" name="Performance %" stroke="#8b5cf6" strokeWidth={3} dot={{ r: 4 }} />
                      </LineChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="emptyChart">No trend data available</div>
                  )}
                </div>
              </section>

              <div className="roleGrid">
                <section className="panel rolePanel">
                  <div className="panel__title">Weak Subjects</div>
                  <div className="chips chips--large">
                    {weakSubjects.map((subject) => (
                      <span className="chip" key={subject.subject_id}>
                        {subject.subject_name} ({formatPercent(subject.average_percentage)})
                      </span>
                    ))}
                    {!weakSubjects.length ? <span className="muted">No weak subjects detected.</span> : null}
                  </div>
                </section>

                <section className="panel rolePanel">
                  <div className="panel__title">Recommendations</div>
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
                    {!recommendations.length ? <div className="muted">No recommendations assigned.</div> : null}
                  </div>
                </section>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}
