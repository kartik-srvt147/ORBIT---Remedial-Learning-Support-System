import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import { useEffect, useState } from "react";
import { getAdminOverview } from "../services/api.js";

const resources = [
  { key: "teachers", title: "Teachers", icon: "T", to: "/admin/teachers" },
  { key: "students", title: "Students", icon: "S", to: "/admin/students" },
  { key: "subjects", title: "Subjects", icon: "B", to: "/admin/subjects" },
  { key: "sections", title: "Sections", icon: "C", to: "/admin/sections" },
];

export default function AdminResources() {
  const [overview, setOverview] = useState(null);

  useEffect(() => {
    getAdminOverview().then(setOverview).catch(() => setOverview(null));
  }, []);

  const counts = overview?.counts || {
    teachers: 0,
    students: 0,
    subjects: 0,
    sections: 0,
  };

  return (
    <div className="page">
      <header className="topbar">
        <div>
          <div className="kicker">Admin</div>
          <h1 className="h1">Manage Resources</h1>
          <p className="muted">Create and maintain teachers, students, subjects, and sections.</p>
        </div>
      </header>

      <div className="resourceGrid">
        {resources.map((resource, idx) => (
          <motion.div key={resource.key} initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: idx * 0.05 }}>
            <Link className="resourceCard" to={resource.to}>
              <div className="resourceIcon">{resource.icon}</div>
              <div>
                <div className="resourceTitle">{resource.title}</div>
                <div className="muted">{counts[resource.key] ?? 0} records</div>
              </div>
              <span className="pill">Open</span>
            </Link>
          </motion.div>
        ))}
      </div>
    </div>
  );
}
