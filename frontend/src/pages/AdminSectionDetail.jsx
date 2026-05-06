import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { assignStudentToSection, assignTeacherToSubjectSection, getAdminSection } from "../services/api.js";

export default function AdminSectionDetail() {
  const { id } = useParams();
  const [section, setSection] = useState(null);
  const [studentId, setStudentId] = useState("");
  const [teacherForm, setTeacherForm] = useState({ teacher_id: "", subject_id: "" });
  const [notice, setNotice] = useState("");

  const load = async () => {
    const data = await getAdminSection(id);
    setSection(data.section);
  };

  useEffect(() => {
    load();
  }, [id]);

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Section Detail</div>
          <h1 className="h1">Class {section?.class_name || "-"} - Section {section?.section_name || "-"}</h1>
          <p className="muted">Students, teachers, and subjects assigned to this section.</p>
        </div>
      </header>

      {notice ? <div className="panel"><div className="strong">{notice}</div></div> : null}

      <div className="roleGrid">
        <section className="panel rolePanel">
          <div className="panel__title">Students</div>
          <div className="miniList">
            {(section?.students || []).map((student) => (
              <div className="miniItem" key={student.id}>
                <div>
                  <div className="strong">{student.name}</div>
                  <div className="muted">Roll {student.roll_number}</div>
                </div>
              </div>
            ))}
          </div>
          <form className="quickLinks quickLinks--spaced" onSubmit={async (e) => {
            e.preventDefault();
            await assignStudentToSection({ student_id: Number(studentId), section_id: Number(id) });
            setStudentId("");
            setNotice("Student assigned.");
            await load();
          }}>
            <input className="authInput" value={studentId} onChange={(e) => setStudentId(e.target.value)} placeholder="Student ID" required />
            <button className="btn btn--primary" type="submit">Add Student</button>
          </form>
        </section>

        <section className="panel rolePanel">
          <div className="panel__title">Teachers & Subjects</div>
          <div className="miniList">
            {(section?.teacher_subjects || []).map((assignment) => (
              <div className="miniItem" key={assignment.id}>
                <div>
                  <div className="strong">{assignment.teacher?.name}</div>
                  <div className="muted">{assignment.subject?.name}</div>
                </div>
              </div>
            ))}
          </div>
          <form className="quickLinks quickLinks--spaced" onSubmit={async (e) => {
            e.preventDefault();
            await assignTeacherToSubjectSection({
              teacher_id: Number(teacherForm.teacher_id),
              subject_id: Number(teacherForm.subject_id),
              section_id: Number(id),
            });
            setTeacherForm({ teacher_id: "", subject_id: "" });
            setNotice("Teacher and subject assigned.");
            await load();
          }}>
            <input className="authInput" value={teacherForm.teacher_id} onChange={(e) => setTeacherForm((f) => ({ ...f, teacher_id: e.target.value }))} placeholder="Teacher ID" required />
            <input className="authInput" value={teacherForm.subject_id} onChange={(e) => setTeacherForm((f) => ({ ...f, subject_id: e.target.value }))} placeholder="Subject ID" required />
            <button className="btn btn--primary" type="submit">Assign</button>
          </form>
        </section>
      </div>
    </div>
  );
}
