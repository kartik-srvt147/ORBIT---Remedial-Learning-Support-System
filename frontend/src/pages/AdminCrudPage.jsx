import { AnimatePresence, motion } from "framer-motion";
import { Link, useParams } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import {
  createSection,
  createStudent,
  createSubject,
  createTeacher,
  deleteSection,
  deleteStudent,
  deleteSubject,
  deleteTeacher,
  getAdminSections,
  getAdminStudents,
  getAdminSubjects,
  getAdminTeachers,
  updateSection,
  updateStudent,
  updateSubject,
  updateTeacher,
} from "../services/api.js";

const configs = {
  teachers: {
    title: "Teachers",
    empty: { name: "", email: "", password: "", assignment_subject_id: "", assignment_section_id: "" },
    fields: [
      ["name", "Name"],
      ["email", "Email"],
      ["password", "Password", false],
      ["assignment_subject_id", "Subject ID", false],
      ["assignment_section_id", "Section ID", false],
    ],
    list: getAdminTeachers,
    create: (payload) =>
      createTeacher({
        name: payload.name,
        email: payload.email,
        password: payload.password || undefined,
        assignments: payload.assignment_subject_id && payload.assignment_section_id
          ? [{ subject_id: Number(payload.assignment_subject_id), section_id: Number(payload.assignment_section_id) }]
          : [],
      }),
    update: updateTeacher,
    remove: deleteTeacher,
  },
  students: {
    title: "Students",
    empty: { name: "", email: "", password: "", teacher_id: "", section_id: "", roll_number: "" },
    fields: [["name", "Name"], ["email", "Email"], ["password", "Password", false], ["teacher_id", "Teacher ID"], ["section_id", "Section ID"], ["roll_number", "Roll Number"]],
    list: getAdminStudents,
    create: createStudent,
    update: updateStudent,
    remove: deleteStudent,
  },
  subjects: {
    title: "Subjects",
    empty: { name: "" },
    fields: [["name", "Subject Name"]],
    list: getAdminSubjects,
    create: createSubject,
    update: updateSubject,
    remove: deleteSubject,
  },
  sections: {
    title: "Sections",
    empty: { class_name: "", section_name: "" },
    fields: [["class_name", "Class"], ["section_name", "Section"]],
    list: getAdminSections,
    create: createSection,
    update: updateSection,
    remove: deleteSection,
  },
};

function Modal({ title, children, onClose }) {
  return (
    <div className="modalOverlay">
      <motion.div className="modalPanel" initial={{ opacity: 0, scale: 0.96 }} animate={{ opacity: 1, scale: 1 }}>
        <div className="modalHead">
          <div className="panel__title">{title}</div>
          <button className="btn" type="button" onClick={onClose}>Close</button>
        </div>
        {children}
      </motion.div>
    </div>
  );
}

function ConfirmModal({ item, onConfirm, onCancel }) {
  return (
    <Modal title="Confirm delete" onClose={onCancel}>
      <p className="muted">Delete this record? This action cannot be undone.</p>
      <div className="quickLinks quickLinks--spaced">
        <button className="btn btn--primary" type="button" onClick={onCancel}>Cancel</button>
        <button className="btn btn--danger" type="button" onClick={() => onConfirm(item)}>Delete</button>
      </div>
    </Modal>
  );
}

export default function AdminCrudPage() {
  const { resource } = useParams();
  const config = configs[resource] || configs.teachers;
  const [items, setItems] = useState([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState("");
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(config.empty);
  const [confirm, setConfirm] = useState(null);

  const load = async () => {
    setLoading(true);
    const data = await config.list({ search, limit: 50 });
    setItems(data?.[resource] || data?.data || []);
    setLoading(false);
  };

  useEffect(() => {
    setForm(config.empty);
    setEditing(null);
    setConfirm(null);
    load().catch(() => setLoading(false));
  }, [resource]);

  useEffect(() => {
    const timer = window.setTimeout(() => load().catch(() => setLoading(false)), 250);
    return () => window.clearTimeout(timer);
  }, [search]);

  const title = config.title;
  const rows = useMemo(() => items, [items]);

  const openEdit = (item) => {
    setEditing(item);
    setForm({
      ...config.empty,
      ...item,
      email: item.email || item.user?.email || "",
      teacher_id: item.teacher_id || "",
      section_id: item.sections?.[0]?.id || "",
    });
  };

  const submit = async (e) => {
    e.preventDefault();
    const payload = { ...form };
    if (!payload.password) delete payload.password;
    if (editing) {
      await config.update(editing.id, payload);
      setNotice(`${title.slice(0, -1)} updated.`);
    } else {
      const res = await config.create(payload);
      setNotice(res?.default_password ? `Created. Default password: ${res.default_password}` : "Created.");
    }
    setEditing(null);
    setForm(config.empty);
    await load();
  };

  const remove = async (item) => {
    await config.remove(item.id);
    setConfirm(null);
    setNotice("Deleted.");
    await load();
  };

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Manage Resources</div>
          <h1 className="h1">{title}</h1>
          <p className="muted">Search, add, edit, and delete {title.toLowerCase()}.</p>
        </div>
        <button className="btn btn--primary" type="button" onClick={() => setEditing({})}>Add {title.slice(0, -1)}</button>
      </header>

      <section className="filterBar">
        <label className="filterField filterField--wide">
          <span>Search</span>
          <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={`Search ${title.toLowerCase()}`} />
        </label>
      </section>

      {notice ? <div className="panel"><div className="strong">{notice}</div></div> : null}

      <div className="panel">
        {loading ? <div className="muted">Loading...</div> : null}
        {!loading && !rows.length ? <div className="muted">No records found.</div> : null}
        <div className="resourceTable">
          {rows.map((item) => (
            <div className="resourceRow" key={item.id}>
              <div>
                <div className="strong">
                  {resource === "sections" ? (
                    <Link className="tableLink" to={`/admin/sections/${item.id}`}>Class {item.class_name} - {item.section_name}</Link>
                  ) : item.name}
                </div>
                <div className="muted">
                  {resource === "teachers" ? item.email : null}
                  {resource === "students" ? `Class ${item.class} - Section ${item.section} - Roll ${item.roll_number}` : null}
                  {resource === "subjects" ? `${item.teacher_subjects_count || 0} teacher assignments` : null}
                  {resource === "sections" ? `${item.students_count || 0} students` : null}
                </div>
                {resource === "teachers" ? (
                  <div className="chips">
                    {(item.teacher_subjects || []).map((a) => <span className="chip" key={a.id}>{a.subject?.name} / {a.section?.class_name}-{a.section?.section_name}</span>)}
                  </div>
                ) : null}
              </div>
              <div className="rowActions">
                <button className="btn" type="button" onClick={() => openEdit(item)}>Edit</button>
                <button className="btn btn--danger" type="button" onClick={() => setConfirm(item)}>Delete</button>
              </div>
            </div>
          ))}
        </div>
      </div>

      <AnimatePresence>
        {editing ? (
          <Modal title={`${editing.id ? "Edit" : "Add"} ${title.slice(0, -1)}`} onClose={() => setEditing(null)}>
            <form className="adminForm" onSubmit={submit}>
              <div className="adminForm__grid">
                {config.fields.map(([name, label, required = true]) => (
                  <label className="authLabel" key={name}>
                    {label}
                    <input
                      className="authInput"
                      name={name}
                      value={form[name] || ""}
                      onChange={(e) => setForm((f) => ({ ...f, [name]: e.target.value }))}
                      required={required && name !== "password"}
                    />
                  </label>
                ))}
              </div>
              <button className="btn btn--primary" type="submit">Save</button>
            </form>
          </Modal>
        ) : null}
        {confirm ? <ConfirmModal item={confirm} onCancel={() => setConfirm(null)} onConfirm={remove} /> : null}
      </AnimatePresence>
    </div>
  );
}
