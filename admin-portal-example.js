/**
 * VitalVida Admin Portal - React Implementation Example
 * 
 * This example demonstrates how to build a scalable admin interface
 * that can grow with the Laravel backend. The structure is designed
 * to be modular and easily expandable for future features.
 */

import React, { useState, useEffect, useContext } from 'react';
import axios from 'axios';

// ========================================
// API CONFIGURATION
// ========================================

const API_BASE_URL = 'http://localhost:8000/api';
const API_VERSION = 'v1';

// Configure axios defaults
axios.defaults.baseURL = API_BASE_URL;
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

// Add auth token to requests
axios.interceptors.request.use(config => {
    const token = localStorage.getItem('admin_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Handle auth errors
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('admin_token');
            window.location.href = '/admin/login';
        }
        return Promise.reject(error);
    }
);

// ========================================
// CONTEXT PROVIDERS
// ========================================

const AdminContext = React.createContext();

export const AdminProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [notifications, setNotifications] = useState([]);

    useEffect(() => {
        checkAuth();
    }, []);

    const checkAuth = async () => {
        try {
            const token = localStorage.getItem('admin_token');
            if (token) {
                const response = await axios.get('/auth/profile');
                setUser(response.data.data);
            }
        } catch (error) {
            console.error('Auth check failed:', error);
        } finally {
            setLoading(false);
        }
    };

    const login = async (credentials) => {
        const response = await axios.post('/auth/login', credentials);
        const { token, user } = response.data.data;
        localStorage.setItem('admin_token', token);
        setUser(user);
        return response.data;
    };

    const logout = async () => {
        try {
            await axios.post('/auth/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            localStorage.removeItem('admin_token');
            setUser(null);
        }
    };

    const addNotification = (message, type = 'info') => {
        const id = Date.now();
        setNotifications(prev => [...prev, { id, message, type }]);
        setTimeout(() => {
            setNotifications(prev => prev.filter(n => n.id !== id));
        }, 5000);
    };

    return (
        <AdminContext.Provider value={{
            user,
            loading,
            login,
            logout,
            notifications,
            addNotification
        }}>
            {children}
        </AdminContext.Provider>
    );
};

export const useAdmin = () => useContext(AdminContext);

// ========================================
// API SERVICES
// ========================================

export const AdminAPI = {
    // Dashboard
    getDashboard: () => axios.get('/admin/dashboard'),
    getSystemMetrics: () => axios.get('/admin/system/metrics'),

    // User Management
    getUsers: (params = {}) => axios.get('/admin/users', { params }),
    getUser: (id) => axios.get(`/admin/users/${id}`),
    createUser: (data) => axios.post('/admin/users', data),
    updateUser: (id, data) => axios.put(`/admin/users/${id}`, data),
    deleteUser: (id) => axios.delete(`/admin/users/${id}`),
    activateUser: (id) => axios.post(`/admin/users/${id}/activate`),
    deactivateUser: (id) => axios.post(`/admin/users/${id}/deactivate`),

    // KYC Management
    getPendingKyc: (params = {}) => axios.get('/admin/kyc/pending', { params }),
    getAllKyc: (params = {}) => axios.get('/admin/kyc/applications', { params }),
    approveKyc: (userId) => axios.post(`/admin/kyc/${userId}/approve`),
    rejectKyc: (userId, reason) => axios.post(`/admin/kyc/${userId}/reject`, { reason }),
    getKycStats: () => axios.get('/admin/kyc/stats'),
    bulkApproveKyc: (userIds) => axios.post('/admin/kyc/bulk-approve', { user_ids: userIds }),
    bulkRejectKyc: (userIds, reason) => axios.post('/admin/kyc/bulk-reject', { user_ids: userIds, reason }),

    // Role Management
    getRoles: () => axios.get('/admin/roles'),
    getUsersByRole: (role, params = {}) => axios.get(`/admin/roles/${role}/users`, { params }),
    bulkAssignRole: (userIds, role) => axios.post('/admin/roles/bulk-assign', { user_ids: userIds, role }),
    getRoleStats: () => axios.get('/admin/roles/stats'),
    getRolePermissions: () => axios.get('/admin/roles/permissions'),
    getUserActivityByRole: (role, params = {}) => axios.get(`/admin/roles/${role}/activities`, { params }),
    getRolePerformance: () => axios.get('/admin/roles/performance'),

    // Audit Logs
    getAuditLogs: (params = {}) => axios.get('/admin/audit-logs', { params }),

    // System Configuration
    getSystemConfig: () => axios.get('/admin/system/config'),
    getSystemHealth: () => axios.get('/admin/system/health'),
    getPerformanceMetrics: () => axios.get('/admin/system/performance'),
    getLogsSummary: () => axios.get('/admin/system/logs'),
    clearCache: (cacheType = 'all') => axios.post('/admin/system/clear-cache', { cache_type: cacheType }),
    getBackupStatus: () => axios.get('/admin/system/backups'),

    // API Version Info
    getVersions: () => axios.get('/admin/versions'),
    getStatus: () => axios.get('/admin/status'),
};

// ========================================
// COMPONENTS
// ========================================

// Login Component
export const AdminLogin = () => {
    const { login, addNotification } = useAdmin();
    const [credentials, setCredentials] = useState({ email: '', password: '' });
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await login(credentials);
            addNotification('Login successful!', 'success');
        } catch (error) {
            addNotification(error.response?.data?.message || 'Login failed', 'error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50">
            <div className="max-w-md w-full space-y-8">
                <div>
                    <h2 className="text-center text-3xl font-extrabold text-gray-900">
                        VitalVida Admin Portal
                    </h2>
                </div>
                <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
                    <div>
                        <input
                            type="email"
                            required
                            className="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                            placeholder="Email address"
                            value={credentials.email}
                            onChange={(e) => setCredentials({ ...credentials, email: e.target.value })}
                        />
                    </div>
                    <div>
                        <input
                            type="password"
                            required
                            className="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                            placeholder="Password"
                            value={credentials.password}
                            onChange={(e) => setCredentials({ ...credentials, password: e.target.value })}
                        />
                    </div>
                    <div>
                        <button
                            type="submit"
                            disabled={loading}
                            className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {loading ? 'Signing in...' : 'Sign in'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

// Dashboard Component
export const AdminDashboard = () => {
    const { user } = useAdmin();
    const [dashboardData, setDashboardData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadDashboard();
    }, []);

    const loadDashboard = async () => {
        try {
            const response = await AdminAPI.getDashboard();
            setDashboardData(response.data.data);
        } catch (error) {
            console.error('Failed to load dashboard:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) return <div>Loading dashboard...</div>;

    return (
        <div className="p-6">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                <p className="text-gray-600">Welcome back, {user?.name}</p>
            </div>

            {dashboardData && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <StatCard title="Total Users" value={dashboardData.stats.total_users} />
                    <StatCard title="Active Users" value={dashboardData.stats.active_users} />
                    <StatCard title="Pending KYC" value={dashboardData.stats.pending_kyc} />
                    <StatCard title="Total Orders" value={dashboardData.stats.total_orders} />
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <RecentActivities />
                <UserDistribution />
            </div>
        </div>
    );
};

// User Management Component
export const UserManagement = () => {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({});
    const [selectedUsers, setSelectedUsers] = useState([]);
    const { addNotification } = useAdmin();

    useEffect(() => {
        loadUsers();
    }, [filters]);

    const loadUsers = async () => {
        try {
            const response = await AdminAPI.getUsers(filters);
            setUsers(response.data.data);
        } catch (error) {
            addNotification('Failed to load users', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleBulkAction = async (action) => {
        if (selectedUsers.length === 0) {
            addNotification('Please select users first', 'warning');
            return;
        }

        try {
            switch (action) {
                case 'activate':
                    await Promise.all(selectedUsers.map(id => AdminAPI.activateUser(id)));
                    addNotification(`${selectedUsers.length} users activated`, 'success');
                    break;
                case 'deactivate':
                    await Promise.all(selectedUsers.map(id => AdminAPI.deactivateUser(id)));
                    addNotification(`${selectedUsers.length} users deactivated`, 'success');
                    break;
                case 'delete':
                    if (confirm(`Are you sure you want to delete ${selectedUsers.length} users?`)) {
                        await Promise.all(selectedUsers.map(id => AdminAPI.deleteUser(id)));
                        addNotification(`${selectedUsers.length} users deleted`, 'success');
                    }
                    break;
            }
            setSelectedUsers([]);
            loadUsers();
        } catch (error) {
            addNotification('Bulk action failed', 'error');
        }
    };

    return (
        <div className="p-6">
            <div className="mb-6 flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900">User Management</h1>
                <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Add User
                </button>
            </div>

            {/* Filters */}
            <div className="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <input
                    type="text"
                    placeholder="Search users..."
                    className="border rounded-md px-3 py-2"
                    onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                />
                <select
                    className="border rounded-md px-3 py-2"
                    onChange={(e) => setFilters({ ...filters, role: e.target.value })}
                >
                    <option value="">All Roles</option>
                    <option value="superadmin">Super Admin</option>
                    <option value="ceo">CEO</option>
                    <option value="cfo">CFO</option>
                    <option value="accountant">Accountant</option>
                    <option value="production">Production</option>
                    <option value="inventory">Inventory</option>
                    <option value="telesales">Telesales</option>
                    <option value="da">Delivery Agent</option>
                </select>
                <select
                    className="border rounded-md px-3 py-2"
                    onChange={(e) => setFilters({ ...filters, kyc_status: e.target.value })}
                >
                    <option value="">All KYC Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select
                    className="border rounded-md px-3 py-2"
                    onChange={(e) => setFilters({ ...filters, is_active: e.target.value })}
                >
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            {/* Bulk Actions */}
            {selectedUsers.length > 0 && (
                <div className="mb-4 p-4 bg-blue-50 rounded-md">
                    <div className="flex items-center justify-between">
                        <span>{selectedUsers.length} users selected</span>
                        <div className="space-x-2">
                            <button
                                onClick={() => handleBulkAction('activate')}
                                className="bg-green-600 text-white px-3 py-1 rounded text-sm"
                            >
                                Activate
                            </button>
                            <button
                                onClick={() => handleBulkAction('deactivate')}
                                className="bg-yellow-600 text-white px-3 py-1 rounded text-sm"
                            >
                                Deactivate
                            </button>
                            <button
                                onClick={() => handleBulkAction('delete')}
                                className="bg-red-600 text-white px-3 py-1 rounded text-sm"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Users Table */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input
                                    type="checkbox"
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            setSelectedUsers(users.data.map(u => u.id));
                                        } else {
                                            setSelectedUsers([]);
                                        }
                                    }}
                                />
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                KYC Status
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {users.data?.map((user) => (
                            <tr key={user.id}>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        checked={selectedUsers.includes(user.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setSelectedUsers([...selectedUsers, user.id]);
                                            } else {
                                                setSelectedUsers(selectedUsers.filter(id => id !== user.id));
                                            }
                                        }}
                                    />
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">{user.name}</div>
                                        <div className="text-sm text-gray-500">{user.email}</div>
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {user.role}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                        user.kyc_status === 'approved' ? 'bg-green-100 text-green-800' :
                                        user.kyc_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-red-100 text-red-800'
                                    }`}>
                                        {user.kyc_status}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                        user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    }`}>
                                        {user.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button className="text-indigo-600 hover:text-indigo-900 mr-2">Edit</button>
                                    <button className="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

// KYC Management Component
export const KycManagement = () => {
    const [applications, setApplications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedApplications, setSelectedApplications] = useState([]);
    const { addNotification } = useAdmin();

    useEffect(() => {
        loadPendingApplications();
    }, []);

    const loadPendingApplications = async () => {
        try {
            const response = await AdminAPI.getPendingKyc();
            setApplications(response.data.data);
        } catch (error) {
            addNotification('Failed to load KYC applications', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleBulkApprove = async () => {
        if (selectedApplications.length === 0) {
            addNotification('Please select applications first', 'warning');
            return;
        }

        try {
            await AdminAPI.bulkApproveKyc(selectedApplications);
            addNotification(`${selectedApplications.length} applications approved`, 'success');
            setSelectedApplications([]);
            loadPendingApplications();
        } catch (error) {
            addNotification('Bulk approval failed', 'error');
        }
    };

    const handleBulkReject = async () => {
        if (selectedApplications.length === 0) {
            addNotification('Please select applications first', 'warning');
            return;
        }

        const reason = prompt('Enter rejection reason:');
        if (!reason) return;

        try {
            await AdminAPI.bulkRejectKyc(selectedApplications, reason);
            addNotification(`${selectedApplications.length} applications rejected`, 'success');
            setSelectedApplications([]);
            loadPendingApplications();
        } catch (error) {
            addNotification('Bulk rejection failed', 'error');
        }
    };

    return (
        <div className="p-6">
            <div className="mb-6 flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900">KYC Management</h1>
                <div className="space-x-2">
                    <button
                        onClick={handleBulkApprove}
                        disabled={selectedApplications.length === 0}
                        className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 disabled:opacity-50"
                    >
                        Bulk Approve ({selectedApplications.length})
                    </button>
                    <button
                        onClick={handleBulkReject}
                        disabled={selectedApplications.length === 0}
                        className="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 disabled:opacity-50"
                    >
                        Bulk Reject ({selectedApplications.length})
                    </button>
                </div>
            </div>

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input
                                    type="checkbox"
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            setSelectedApplications(applications.data.map(a => a.id));
                                        } else {
                                            setSelectedApplications([]);
                                        }
                                    }}
                                />
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Applicant
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Documents
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Submitted
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {applications.data?.map((application) => (
                            <tr key={application.id}>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        checked={selectedApplications.includes(application.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setSelectedApplications([...selectedApplications, application.id]);
                                            } else {
                                                setSelectedApplications(selectedApplications.filter(id => id !== application.id));
                                            }
                                        }}
                                    />
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">{application.name}</div>
                                        <div className="text-sm text-gray-500">{application.email}</div>
                                        <div className="text-sm text-gray-500">{application.phone}</div>
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {application.role}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="text-sm text-gray-900">
                                        {application.documents_count} documents
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {application.pending_documents} pending
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {new Date(application.created_at).toLocaleDateString()}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button className="text-green-600 hover:text-green-900 mr-2">Approve</button>
                                    <button className="text-red-600 hover:text-red-900">Reject</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

// System Configuration Component
export const SystemConfiguration = () => {
    const [config, setConfig] = useState(null);
    const [health, setHealth] = useState(null);
    const [loading, setLoading] = useState(true);
    const { addNotification } = useAdmin();

    useEffect(() => {
        loadSystemInfo();
    }, []);

    const loadSystemInfo = async () => {
        try {
            const [configResponse, healthResponse] = await Promise.all([
                AdminAPI.getSystemConfig(),
                AdminAPI.getSystemHealth()
            ]);
            setConfig(configResponse.data.data);
            setHealth(healthResponse.data.data);
        } catch (error) {
            addNotification('Failed to load system information', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleClearCache = async (cacheType = 'all') => {
        try {
            await AdminAPI.clearCache(cacheType);
            addNotification('Cache cleared successfully', 'success');
        } catch (error) {
            addNotification('Failed to clear cache', 'error');
        }
    };

    if (loading) return <div>Loading system information...</div>;

    return (
        <div className="p-6">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">System Configuration</h1>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* System Configuration */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-semibold mb-4">System Configuration</h2>
                    {config && (
                        <div className="space-y-4">
                            <div>
                                <h3 className="font-medium">Application</h3>
                                <p className="text-sm text-gray-600">Name: {config.system.app_name}</p>
                                <p className="text-sm text-gray-600">Environment: {config.system.app_env}</p>
                                <p className="text-sm text-gray-600">Timezone: {config.system.timezone}</p>
                            </div>
                            <div>
                                <h3 className="font-medium">Database</h3>
                                <p className="text-sm text-gray-600">Connection: {config.database.connection}</p>
                                <p className="text-sm text-gray-600">Database: {config.database.database}</p>
                            </div>
                        </div>
                    )}
                </div>

                {/* System Health */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-semibold mb-4">System Health</h2>
                    {health && (
                        <div className="space-y-4">
                            <div className={`p-3 rounded-md ${
                                health.overall_status === 'healthy' ? 'bg-green-50 text-green-800' : 'bg-yellow-50 text-yellow-800'
                            }`}>
                                <span className="font-medium">Overall Status: {health.overall_status}</span>
                            </div>
                            <div>
                                <h3 className="font-medium">Database</h3>
                                <p className="text-sm text-gray-600">Status: {health.database.connection.status}</p>
                                <p className="text-sm text-gray-600">Tables: {health.database.tables}</p>
                            </div>
                            <div>
                                <h3 className="font-medium">Storage</h3>
                                <p className="text-sm text-gray-600">Usage: {Math.round(health.storage.disk_usage.usage_percentage)}%</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Cache Management */}
            <div className="mt-6 bg-white shadow rounded-lg p-6">
                <h2 className="text-lg font-semibold mb-4">Cache Management</h2>
                <div className="flex space-x-2">
                    <button
                        onClick={() => handleClearCache('all')}
                        className="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700"
                    >
                        Clear All Cache
                    </button>
                    <button
                        onClick={() => handleClearCache('config')}
                        className="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700"
                    >
                        Clear Config Cache
                    </button>
                    <button
                        onClick={() => handleClearCache('route')}
                        className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                    >
                        Clear Route Cache
                    </button>
                </div>
            </div>
        </div>
    );
};

// ========================================
// UTILITY COMPONENTS
// ========================================

const StatCard = ({ title, value }) => (
    <div className="bg-white overflow-hidden shadow rounded-lg">
        <div className="p-5">
            <div className="flex items-center">
                <div className="flex-shrink-0">
                    <div className="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </div>
                <div className="ml-5 w-0 flex-1">
                    <dl>
                        <dt className="text-sm font-medium text-gray-500 truncate">{title}</dt>
                        <dd className="text-lg font-medium text-gray-900">{value}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
);

const RecentActivities = () => {
    const [activities, setActivities] = useState([]);

    useEffect(() => {
        // Load recent activities
        AdminAPI.getAuditLogs({ per_page: 10 }).then(response => {
            setActivities(response.data.data.data);
        });
    }, []);

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <h2 className="text-lg font-semibold mb-4">Recent Activities</h2>
            <div className="space-y-4">
                {activities.map((activity) => (
                    <div key={activity.id} className="flex items-start space-x-3">
                        <div className="flex-shrink-0">
                            <div className="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm text-gray-900">{activity.action}</p>
                            <p className="text-xs text-gray-500">
                                {activity.user_name} • {new Date(activity.created_at).toLocaleString()}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

const UserDistribution = () => {
    const [distribution, setDistribution] = useState({});

    useEffect(() => {
        // Load user distribution
        AdminAPI.getRoleStats().then(response => {
            setDistribution(response.data.data.stats);
        });
    }, []);

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <h2 className="text-lg font-semibold mb-4">User Distribution by Role</h2>
            <div className="space-y-3">
                {Object.entries(distribution).map(([role, stats]) => (
                    <div key={role} className="flex justify-between items-center">
                        <span className="text-sm font-medium text-gray-900">{stats.label}</span>
                        <span className="text-sm text-gray-500">{stats.total_users} users</span>
                    </div>
                ))}
            </div>
        </div>
    );
};

// ========================================
// MAIN ADMIN APP COMPONENT
// ========================================

export const AdminApp = () => {
    const { user, loading, logout } = useAdmin();
    const [currentPage, setCurrentPage] = useState('dashboard');

    if (loading) {
        return <div>Loading...</div>;
    }

    if (!user) {
        return <AdminLogin />;
    }

    const renderPage = () => {
        switch (currentPage) {
            case 'dashboard':
                return <AdminDashboard />;
            case 'users':
                return <UserManagement />;
            case 'kyc':
                return <KycManagement />;
            case 'system':
                return <SystemConfiguration />;
            default:
                return <AdminDashboard />;
        }
    };

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Navigation */}
            <nav className="bg-white shadow">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <h1 className="text-xl font-bold text-gray-900">VitalVida Admin</h1>
                            </div>
                            <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                                <button
                                    onClick={() => setCurrentPage('dashboard')}
                                    className={`${
                                        currentPage === 'dashboard'
                                            ? 'border-indigo-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                    } whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm`}
                                >
                                    Dashboard
                                </button>
                                <button
                                    onClick={() => setCurrentPage('users')}
                                    className={`${
                                        currentPage === 'users'
                                            ? 'border-indigo-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                    } whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm`}
                                >
                                    Users
                                </button>
                                <button
                                    onClick={() => setCurrentPage('kyc')}
                                    className={`${
                                        currentPage === 'kyc'
                                            ? 'border-indigo-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                    } whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm`}
                                >
                                    KYC Management
                                </button>
                                <button
                                    onClick={() => setCurrentPage('system')}
                                    className={`${
                                        currentPage === 'system'
                                            ? 'border-indigo-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                    } whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm`}
                                >
                                    System
                                </button>
                            </div>
                        </div>
                        <div className="flex items-center">
                            <span className="text-sm text-gray-700 mr-4">{user.name}</span>
                            <button
                                onClick={logout}
                                className="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                {renderPage()}
            </main>
        </div>
    );
};

// ========================================
// USAGE EXAMPLE
// ========================================

/*
// In your main App.js or index.js:
import React from 'react';
import { AdminProvider } from './admin-portal-example';
import { AdminApp } from './admin-portal-example';

function App() {
    return (
        <AdminProvider>
            <AdminApp />
        </AdminProvider>
    );
}

export default App;
*/

// ========================================
// TESTING INSTRUCTIONS
// ========================================

/*
To test the admin portal:

1. Start your Laravel server:
   php artisan serve

2. Create a superadmin user in your database:
   INSERT INTO users (name, email, password, role, is_active, email_verified_at, created_at, updated_at)
   VALUES ('Super Admin', 'admin@vitalvida.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, NOW(), NOW(), NOW());

3. Install and run the React app:
   npm install
   npm start

4. Login with:
   Email: admin@vitalvida.com
   Password: password

5. Test the different features:
   - Dashboard overview
   - User management with filters and bulk operations
   - KYC management with bulk approve/reject
   - System configuration and cache management
   - Role-based access control

6. Test API endpoints directly:
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/admin/dashboard
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/admin/users
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/admin/kyc/pending

7. Test versioned APIs:
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/admin/v2/analytics/dashboard
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/admin/v3/ai/insights

The admin portal is designed to be easily expandable. You can add new features by:
- Creating new controllers in the Admin namespace
- Adding new routes to the versioned API structure
- Creating new React components following the same patterns
- Extending the AdminAPI service with new methods
*/ 