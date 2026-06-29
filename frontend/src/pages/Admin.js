import React, { useState, useEffect } from 'react';
import { fetchAdminData } from '../services/adminService';
import '../styles/Admin.css';

function Admin() {
  const [admins, setAdmins] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const loadAdminData = async () => {
      try {
        setLoading(true);
        const data = await fetchAdminData();
        setAdmins(data.admins || []);
       setError(null);
      } catch (err) {
        setError('Failed to load admin data');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    loadAdminData();
  }, []);

  if (loading) {
    return <div className="admin-page"><p>Loading admin data...</p></div>;
  }

  if (error) {
    return <div className="admin-page"><p className="error">{error}</p></div>;
  }

  return (
    <div className="admin-page">
      <div className="admin-container">
        <h1>Admin Panel</h1>

        <div className="admins-section">
          <h2>Administrators ({admins.length})</h2>
          {admins.length > 0 ? (
            <div className="admins-table">
              <table>
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined Date</th>
                  </tr>
                </thead>
                <tbody>
                  {admins.map((admin) => (
                    <tr key={admin.id}>
                      <td>{admin.name}</td>
                      <td>{admin.email}</td>
                      <td>{admin.role}</td>
                      <td className={`status ${admin.status.toLowerCase()}`}>
                        {admin.status}
                      </td>
                      <td>{admin.joinedDate}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p>No administrators found.</p>
          )}
        </div>
      </div>
    </div>
  );
}

export default Admin;
