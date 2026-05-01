import { AnimatePresence, motion } from "framer-motion";
import { Link } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { getTeacherSections, getTeacherStudents, getTeacherSubjectPerformance } from "../services/api.js";

function StatCard({ title, value, subtitle, tone = "default", delay = 0 }) {
  const toneClass = tone === "danger" ? "card--danger" : tone === "warn" ? "card--warn" : "card--default";

  return (
    <motion.div className={`card ${toneClass}`} initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, delay }}>
      <div className="card__title">{title}</div>
      <div className="card__value">{value}</div>
      {subtitle ? <div className="card__subtitle">{subtitle}</div> : null}
    </motion.div>
  );
}

function formatPercent(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return "-";
  return `${Number(value).toFixed(2)}%`;
}

export default function TeacherDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [sections, setSections] = useState([]);
  const [students, setStudents] = useState([]);
  const [subjectPerformance, setSubjectPerformance] = useState([]);

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const [sectionData, studentData, performanceData] = await Promise.all([
          getTeacherSections(),
          getTeacherStudents(),
          getTeacherSubjectPerformance(),
        ]);
        if (!active) return;
        setSections(sectionData?.sections || []);
        setStudents(studentData?.students || []);
        setSubjectPerformance(performanceData?.subject_performance || []);
      } catch (e) {
        if (!active) return;
        setError(e?.message || "Failed to load teacher dashboard");
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

  const criticalCount = useMemo(
    () =>
      students.filter((student) => {
        const avg = Number(student.average_marks);
        const attendance = Number(student.attendance_percentage);
        return (Number.isFinite(avg) && avg < 30) || (Number.isFinite(attendance) && attendance < 60);
      }).length,
    [students]
  );

  const cards = [
    { title: "Assigned Sections", value: sections.length, subtitle: "Your active classes" },
    { title: "Students", value: students.length, subtitle: "In assigned sections" },
    { title: "Need Support", value: students.filter((s) => s.is_slow_learner).length, subtitle: "Flagged learners", tone: "danger" },
    { title: "Critical", value: criticalCount, subtitle: "Immediate follow-up", tone: "warn" },
  ];

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Teacher</div>
          <h1 className="h1">Teacher Dashboard</h1>
          <p className="muted">Assigned sections, students, and subject-wise performance.</p>
        </div>
      </header>

      <main>
        <AnimatePresence mode="wait">
          {loading ? (
            <motion.div key="loading" className="panel" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              <div className="skeleton-grid">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="skeleton" />)}</div>
            </motion.div>
          ) : error ? (
            <motion.div key="error" className="panel panel--error" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <div className="error-title">Could not load teacher dashboard</div>
              <div className="error-msg">{error}</div>
            </motion.div>
          ) : (
            <motion.div key="content" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              <div className="grid">{cards.map((card, idx) => <StatCard key={card.title} {...card} delay={idx * 0.05} />)}</div>

              <div className="roleGrid">
                <section className="panel rolePanel">
                  <div className="panel__title">Assigned Sections</div>
                  <div className="miniList">
                    {sections.map((section) => (
                      <div className="miniItem miniItem--stack" key={section.id}>
                        <div className="strong">Class {section.class_name} - Section {section.section_name}</div>
                        <div className="chips">
                          {(section.subjects || []).map((subject) => <span className="chip" key={subject.id}>{subject.name}</span>)}
                        </div>
                      </div>
                    ))}
                    {!sections.length ? <div className="muted">No assigned sections.</div> : null}
                  </div>
                </section>

                <section className="panel rolePanel">
                  <div className="panel__title">Students</div>
                  <div className="miniList">
                    {students.slice(0, 8).map((student) => (
                      <Link className="studentMiniCard" to={`/students/${student.student_id}`} key={student.student_id}>
                        <div>
                          <div className="strong">{student.student_name}</div>
                          <div className="muted">
                            Class {student.class}-{student.section} / Marks {formatPercent(student.average_marks)}
                          </div>
                        </div>
                        <span className={student.is_slow_learner ? "pill pill--danger" : "pill"}>{student.is_slow_learner ? "Risk" : "OK"}</span>
                      </Link>
                    ))}
                    {!students.length ? <div className="muted">No students found.</div> : null}
                  </div>
                </section>
              </div>

              <section className="chartPanel chartPanel--wide">
                <div className="chartPanel__head">
                  <div className="panel__title">Subject-Wise Performance</div>
                  <span className="pill">{subjectPerformance.length} subjects</span>
                </div>
                <div className="chartBox chartBox--tall">
                  {subjectPerformance.length ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={subjectPerformance}>
                        <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.10)" />
                        <XAxis dataKey="subject_name" tick={{ fill: "currentColor", fontSize: 12 }} />
                        <YAxis tick={{ fill: "currentColor", fontSize: 12 }} domain={[0, 100]} />
                        <Tooltip />
                        <Bar dataKey="average_percentage" name="Average %" fill="#8b5cf6" radius={[8, 8, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="emptyChart">No performance data yet</div>
                  )}
                </div>
              </section>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}
