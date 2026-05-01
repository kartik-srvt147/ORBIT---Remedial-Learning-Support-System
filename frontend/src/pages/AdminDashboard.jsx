import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useMemo, useState } from "react";
import {
  createSection,
  createStudent,
  createSubject,
  createTeacher,
  getAdminOverview,
  getDashboardSummary,
} from "../services/api.js";

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

function AdminForm({ title, fields, values, onChange, onSubmit, loading }) {
  return (
    <form className="adminForm" onSubmit={onSubmit}>
      <div className="panel__title">{title}</div>
      <div className="adminForm__grid">
        {fields.map((field) => (
          <label className="authLabel" key={field.name}>
            {field.label}
            <input
              className="authInput"
              name={field.name}
              type={field.type || "text"}
              value={values[field.name] || ""}
              onChange={onChange}
              required={field.required !== false}
            />
          </label>
        ))}
      </div>
      <button className="btn btn--primary" type="submit" disabled={loading}>
        {loading ? "Saving..." : "Create"}
      </button>
    </form>
  );
}

export default function AdminDashboard() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState("");
  const [summary, setSummary] = useState(null);
  const [overview, setOverview] = useState({ sections: [], teachers_per_subject: [] });
  const [teacherForm, setTeacherForm] = useState({ name: "", email: "" });
  const [studentForm, setStudentForm] = useState({ name: "", email: "", teacher_id: "", section_id: "", roll_number: "" });
  const [subjectForm, setSubjectForm] = useState({ name: "" });
  const [sectionForm, setSectionForm] = useState({ class_name: "", section_name: "" });

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const [summaryData, overviewData] = await Promise.all([getDashboardSummary(), getAdminOverview()]);
      setSummary(summaryData);
      setOverview(overviewData);
    } catch (e) {
      setError(e?.message || "Failed to load admin dashboard");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, []);

  const teachers = useMemo(() => {
    const seen = new Map();
    (overview.teachers_per_subject || []).forEach((subject) => {
      (subject.teachers || []).forEach((entry) => {
        if (entry.teacher?.id) seen.set(entry.teacher.id, entry.teacher);
      });
    });
    return Array.from(seen.values());
  }, [overview]);

  const cards = [
    { title: "Sections", value: overview.sections?.length ?? "-", subtitle: "Academic groups" },
    { title: "Students", value: summary?.total_students ?? "-", subtitle: "System enrollment" },
    { title: "Slow Learners", value: summary?.total_slow_learners ?? "-", subtitle: "Need support", tone: "danger" },
    { title: "Teachers", value: teachers.length, subtitle: "Assigned to subjects" },
  ];

  const submit = async (handler, reset, success) => {
    setSaving(true);
    setNotice("");
    setError(null);
    try {
      const result = await handler();
      const password = result?.default_password ? ` Default password: ${result.default_password}` : "";
      setNotice(`${success}${password}`);
      reset();
      await load();
    } catch (e) {
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Administrator</div>
          <h1 className="h1">Admin Dashboard</h1>
          <p className="muted">Create users and manage sections, subjects, and assignments.</p>
        </div>
      </header>

      <main>
        <AnimatePresence mode="wait">
          {loading ? (
            <motion.div key="loading" className="panel" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              <div className="skeleton-grid">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="skeleton" />)}</div>
            </motion.div>
          ) : (
            <motion.div key="content" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              {error ? <div className="panel panel--error"><div className="error-msg">{error}</div></div> : null}
              {notice ? <div className="panel"><div className="strong">{notice}</div></div> : null}

              <div className="grid">{cards.map((card, idx) => <StatCard key={card.title} {...card} delay={idx * 0.05} />)}</div>

              <div className="roleGrid">
                <section className="panel rolePanel">
                  <div className="panel__title">Sections</div>
                  <div className="miniList">
                    {(overview.sections || []).map((section) => (
                      <div className="miniItem" key={section.id}>
                        <div>
                          <div className="strong">Class {section.class_name} - Section {section.section_name}</div>
                          <div className="muted">{section.students_count} students</div>
                        </div>
                        <span className="pill">{section.students_count}</span>
                      </div>
                    ))}
                  </div>
                </section>

                <section className="panel rolePanel">
                  <div className="panel__title">Teachers Per Subject</div>
                  <div className="miniList">
                    {(overview.teachers_per_subject || []).map((subject) => (
                      <div className="miniItem miniItem--stack" key={subject.subject_id}>
                        <div className="strong">{subject.subject_name}</div>
                        <div className="chips">
                          {(subject.teachers || []).map((entry, idx) => (
                            <span className="chip" key={`${subject.subject_id}-${idx}`}>
                              {entry.teacher?.name} / {entry.section?.class_name}-{entry.section?.section_name}
                            </span>
                          ))}
                          {!subject.teachers?.length ? <span className="muted">No teachers assigned.</span> : null}
                        </div>
                      </div>
                    ))}
                  </div>
                </section>
              </div>

              <section className="panel">
                <div className="panel__title">Admin-Only User Creation</div>
                <div className="adminForms">
                  <AdminForm
                    title="Create Teacher"
                    fields={[{ name: "name", label: "Name" }, { name: "email", label: "Email", type: "email" }]}
                    values={teacherForm}
                    onChange={(e) => setTeacherForm((f) => ({ ...f, [e.target.name]: e.target.value }))}
                    loading={saving}
                    onSubmit={(e) => {
                      e.preventDefault();
                      submit(() => createTeacher(teacherForm), () => setTeacherForm({ name: "", email: "" }), "Teacher created.");
                    }}
                  />
                  <AdminForm
                    title="Create Student"
                    fields={[
                      { name: "name", label: "Name" },
                      { name: "email", label: "Email", type: "email" },
                      { name: "teacher_id", label: "Teacher ID" },
                      { name: "section_id", label: "Section ID" },
                      { name: "roll_number", label: "Roll Number", type: "number" },
                    ]}
                    values={studentForm}
                    onChange={(e) => setStudentForm((f) => ({ ...f, [e.target.name]: e.target.value }))}
                    loading={saving}
                    onSubmit={(e) => {
                      e.preventDefault();
                      submit(() => createStudent(studentForm), () => setStudentForm({ name: "", email: "", teacher_id: "", section_id: "", roll_number: "" }), "Student created.");
                    }}
                  />
                  <AdminForm
                    title="Create Subject"
                    fields={[{ name: "name", label: "Subject Name" }]}
                    values={subjectForm}
                    onChange={(e) => setSubjectForm((f) => ({ ...f, [e.target.name]: e.target.value }))}
                    loading={saving}
                    onSubmit={(e) => {
                      e.preventDefault();
                      submit(() => createSubject(subjectForm), () => setSubjectForm({ name: "" }), "Subject created.");
                    }}
                  />
                  <AdminForm
                    title="Create Section"
                    fields={[{ name: "class_name", label: "Class" }, { name: "section_name", label: "Section" }]}
                    values={sectionForm}
                    onChange={(e) => setSectionForm((f) => ({ ...f, [e.target.name]: e.target.value }))}
                    loading={saving}
                    onSubmit={(e) => {
                      e.preventDefault();
                      submit(() => createSection(sectionForm), () => setSectionForm({ class_name: "", section_name: "" }), "Section created.");
                    }}
                  />
                </div>
              </section>
            </motion.div>
          )}
        </AnimatePresence>
      </main>
    </div>
  );
}
