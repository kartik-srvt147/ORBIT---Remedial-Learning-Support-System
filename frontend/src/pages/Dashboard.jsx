import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  getAnalytics,
  getDashboardSummary,
  getStudentPerformanceTrends,
  getStudents,
} from "../services/api.js";

const PIE_COLORS = ["#fb7185", "#8b5cf6"];

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

function ChartPanel({ title, children, aside }) {
  return (
    <section className="chartPanel">
      <div className="chartPanel__head">
        <div className="panel__title">{title}</div>
        {aside}
      </div>
      <div className="chartBox">{children}</div>
    </section>
  );
}

function EmptyChart({ label }) {
  return <div className="emptyChart">{label}</div>;
}

function useDebouncedValue(value, delay = 300) {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(value), delay);
    return () => window.clearTimeout(timer);
  }, [value, delay]);

  return debounced;
}

export default function Dashboard() {
  const [filters, setFilters] = useState({ class: "", section: "", search: "" });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [summary, setSummary] = useState(null);
  const [analytics, setAnalytics] = useState(null);
  const [students, setStudents] = useState([]);
  const [selectedStudentId, setSelectedStudentId] = useState(null);
  const [trend, setTrend] = useState([]);

  const debouncedFilters = useDebouncedValue(filters);

  const params = useMemo(() => {
    return Object.fromEntries(
      Object.entries(debouncedFilters)
        .filter(([, value]) => String(value || "").trim() !== "")
        .map(([key, value]) => [key, String(value).trim()])
    );
  }, [debouncedFilters]);

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const [summaryData, analyticsData, studentData] = await Promise.all([
          getDashboardSummary(params),
          getAnalytics(params),
          getStudents({ ...params, limit: 8 }),
        ]);

        if (!active) return;

        const nextStudents = studentData?.students || studentData?.data || [];
        setSummary(summaryData);
        setAnalytics(analyticsData);
        setStudents(nextStudents);
        setSelectedStudentId((current) => {
          if (current && nextStudents.some((student) => student.student_id === current)) return current;
          return nextStudents[0]?.student_id || null;
        });
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
  }, [params]);

  useEffect(() => {
    let active = true;

    async function loadTrend() {
      if (!selectedStudentId) {
        setTrend([]);
        return;
      }

      try {
        const trendData = await getStudentPerformanceTrends(selectedStudentId);
        if (!active) return;
        setTrend(trendData?.data || []);
      } catch {
        if (!active) return;
        setTrend([]);
      }
    }

    loadTrend();
    return () => {
      active = false;
    };
  }, [selectedStudentId]);

  const subjectPerformance = analytics?.charts?.subject_wise_average_performance || [];
  const pieData = [
    { name: "Slow learners", value: Number(summary?.total_slow_learners || 0) },
    { name: "Normal students", value: Number(summary?.total_normal_students || 0) },
  ];

  const cards = [
    {
      title: "Total Students",
      value: summary?.total_students ?? "-",
      subtitle: "Filtered enrollment",
    },
    {
      title: "Slow Learners",
      value: summary?.total_slow_learners ?? "-",
      subtitle: "Need extra support",
      tone: "danger",
    },
    {
      title: "Normal Students",
      value: summary?.total_normal_students ?? "-",
      subtitle: "Meeting expectations",
    },
    {
      title: "Low Attendance",
      value: summary?.low_attendance_students ?? "-",
      subtitle: "Below 75% attendance",
      tone: "warn",
    },
  ];

  const selectedStudent = students.find((student) => student.student_id === selectedStudentId);

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Student Performance</div>
          <h1 className="h1">Analytics Dashboard</h1>
          <p className="muted">Live performance, search, and intervention signals.</p>
        </div>
      </header>

      <section className="filterBar">
        <label className="filterField">
          <span>Class</span>
          <input
            value={filters.class}
            onChange={(e) => setFilters((current) => ({ ...current, class: e.target.value }))}
            placeholder="Any"
          />
        </label>
        <label className="filterField">
          <span>Section</span>
          <input
            value={filters.section}
            onChange={(e) => setFilters((current) => ({ ...current, section: e.target.value }))}
            placeholder="Any"
          />
        </label>
        <label className="filterField filterField--wide">
          <span>Search students</span>
          <input
            value={filters.search}
            onChange={(e) => setFilters((current) => ({ ...current, search: e.target.value }))}
            placeholder="Name or roll number"
          />
        </label>
        <button className="btn" type="button" onClick={() => setFilters({ class: "", section: "", search: "" })}>
          Clear
        </button>
      </section>

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
              <div className="error-title">Could not load dashboard</div>
              <div className="error-msg">{error}</div>
            </motion.div>
          ) : (
            <motion.div key="content" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              <div className="grid">
                {cards.map((c, idx) => (
                  <StatCard key={c.title} {...c} delay={idx * 0.06} />
                ))}
              </div>

              <div className="chartsGrid">
                <ChartPanel title="Subject Performance">
                  {subjectPerformance.length ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={subjectPerformance}>
                        <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.10)" />
                        <XAxis dataKey="subject_name" tick={{ fill: "currentColor", fontSize: 12 }} />
                        <YAxis tick={{ fill: "currentColor", fontSize: 12 }} />
                        <Tooltip />
                        <Bar dataKey="average_percentage" name="Average %" fill="#8b5cf6" radius={[8, 8, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  ) : (
                    <EmptyChart label="No subject data" />
                  )}
                </ChartPanel>

                <ChartPanel title="Slow vs Normal">
                  {pieData.some((item) => item.value > 0) ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie data={pieData} dataKey="value" nameKey="name" innerRadius={58} outerRadius={92} paddingAngle={4}>
                          {pieData.map((entry, index) => (
                            <Cell key={entry.name} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                          ))}
                        </Pie>
                        <Tooltip />
                      </PieChart>
                    </ResponsiveContainer>
                  ) : (
                    <EmptyChart label="No student split data" />
                  )}
                </ChartPanel>

                <ChartPanel
                  title="Performance Trends"
                  aside={
                    <select
                      className="chartSelect"
                      value={selectedStudentId || ""}
                      onChange={(e) => setSelectedStudentId(Number(e.target.value))}
                    >
                      {students.map((student) => (
                        <option key={student.student_id} value={student.student_id}>
                          {student.student_name}
                        </option>
                      ))}
                    </select>
                  }
                >
                  {trend.length ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <LineChart data={trend}>
                        <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.10)" />
                        <XAxis dataKey="date" tick={{ fill: "currentColor", fontSize: 12 }} />
                        <YAxis tick={{ fill: "currentColor", fontSize: 12 }} domain={[0, 100]} />
                        <Tooltip />
                        <Line type="monotone" dataKey="percentage" name="Performance %" stroke="#fb7185" strokeWidth={3} dot={{ r: 4 }} />
                      </LineChart>
                    </ResponsiveContainer>
                  ) : (
                    <EmptyChart label={selectedStudent ? "No trend data" : "Select a student"} />
                  )}
                </ChartPanel>

                <section className="chartPanel">
                  <div className="chartPanel__head">
                    <div className="panel__title">Student Search Results</div>
                    <span className="pill">{students.length} shown</span>
                  </div>
                  <div className="studentCards">
                    {students.map((student) => (
                      <Link className="studentMiniCard" to={`/students/${student.student_id}`} key={student.student_id}>
                        <div>
                          <div className="strong">{student.student_name}</div>
                          <div className="muted">
                            Class {student.class} - Sec {student.section} - Roll {student.roll_number}
                          </div>
                        </div>
                        <span className={student.is_slow_learner ? "pill pill--danger" : "pill"}>{student.is_slow_learner ? "Risk" : "OK"}</span>
                      </Link>
                    ))}
                    {!students.length ? <div className="muted">No students match the current filters.</div> : null}
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
