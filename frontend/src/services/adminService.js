// Admin service to fetch admin data. Prefer backend API (/api/admins)
// Fallback to static JSON in `/data/admin.json` if API is unavailable.
export const fetchAdminData = async () => {
  const apiUrl = '/api/admins';
  const fallbackUrl = '/data/admin.json';

  try {
    const res = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
    if (res.ok) {
      return await res.json();
    }
    // If API returns non-OK, fallthrough to fallback
    console.warn('Admin API returned non-OK status', res.status);
  } catch (apiErr) {
    console.warn('Admin API request failed, falling back to static JSON:', apiErr.message);
  }

  // Fallback to public JSON file
  try {
    const response = await fetch(fallbackUrl, { headers: { 'Accept': 'application/json' } });
    if (!response.ok) {
      throw new Error(`Fallback HTTP error! status: ${response.status}`);
    }
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error fetching admin data from fallback JSON:', error);
    throw error;
  }
};

const adminService = { fetchAdminData };
export default adminService;
