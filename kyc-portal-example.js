// KYC Portal React Example - How to connect to Laravel API
// This shows the complete flow from KYC submission to approval

// API Configuration
const API_BASE_URL = 'http://localhost:8000/api';

// API Helper Functions
const apiCall = async (endpoint, options = {}) => {
  const url = `${API_BASE_URL}${endpoint}`;
  
  const defaultOptions = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  };

  // Add auth token if available
  const token = localStorage.getItem('auth_token');
  if (token) {
    defaultOptions.headers['Authorization'] = `Bearer ${token}`;
  }

  const config = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...options.headers,
    },
  };

  try {
    const response = await fetch(url, config);
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'API request failed');
    }

    return data;
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
};

// KYC API Functions
export const kycAPI = {
  // 1. Submit KYC Application (Public Route)
  submitKYC: async (formData) => {
    // Note: This uses FormData for file uploads
    const url = `${API_BASE_URL}/kyc/submit`;
    
    const response = await fetch(url, {
      method: 'POST',
      body: formData, // Don't set Content-Type for FormData
    });

    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'KYC submission failed');
    }

    return data;
  },

  // 2. Send OTP for Phone Verification (Public Route)
  sendOtp: async (phone) => {
    return await apiCall('/kyc/send-otp', {
      method: 'POST',
      body: JSON.stringify({ phone }),
    });
  },

  // 3. Verify OTP (Public Route)
  verifyOtp: async (phone, otp) => {
    return await apiCall('/kyc/verify-otp', {
      method: 'POST',
      body: JSON.stringify({ phone, otp }),
    });
  },

  // 4. Check KYC Status (Public Route)
  checkStatus: async (phone) => {
    return await apiCall(`/kyc/status/${phone}`, {
      method: 'GET',
    });
  },

  // 5. Get User's KYC Status (Authenticated)
  getMyStatus: async () => {
    return await apiCall('/kyc/my-status', {
      method: 'GET',
    });
  },

  // 6. Get User's KYC Documents (Authenticated)
  getMyDocuments: async () => {
    return await apiCall('/kyc/my-documents', {
      method: 'GET',
    });
  },

  // 7. Upload Additional Document (Authenticated)
  uploadDocument: async (documentType, file, documentNumber = null, expiryDate = null) => {
    const formData = new FormData();
    formData.append('document_type', documentType);
    formData.append('document', file);
    
    if (documentNumber) {
      formData.append('document_number', documentNumber);
    }
    
    if (expiryDate) {
      formData.append('expiry_date', expiryDate);
    }

    const url = `${API_BASE_URL}/kyc/upload-document`;
    const token = localStorage.getItem('auth_token');
    
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
      body: formData,
    });

    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'Document upload failed');
    }

    return data;
  },

  // 8. Delete Document (Authenticated)
  deleteDocument: async (documentId) => {
    return await apiCall(`/kyc/documents/${documentId}`, {
      method: 'DELETE',
    });
  },
};

// React Component Examples

// 1. KYC Submission Form Component
export const KYCSubmissionForm = () => {
  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    email: '',
    national_id_number: '',
    national_id_expiry: '',
    address: '',
    date_of_birth: '',
    gender: 'male',
  });

  const [files, setFiles] = useState({
    national_id_front: null,
    national_id_back: null,
    selfie: null,
    utility_bill: null,
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // Create FormData for file upload
      const formDataToSend = new FormData();
      
      // Add text fields
      Object.keys(formData).forEach(key => {
        formDataToSend.append(key, formData[key]);
      });

      // Add files
      Object.keys(files).forEach(key => {
        if (files[key]) {
          formDataToSend.append(key, files[key]);
        }
      });

      const response = await kycAPI.submitKYC(formDataToSend);
      
      setSuccess(true);
      console.log('KYC submitted successfully:', response);
      
      // Redirect to OTP verification
      // navigate('/kyc/verify-otp');
      
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleFileChange = (field, file) => {
    setFiles(prev => ({
      ...prev,
      [field]: file,
    }));
  };

  return (
    <form onSubmit={handleSubmit} className="kyc-form">
      <h2>KYC Application</h2>
      
      {error && <div className="error">{error}</div>}
      {success && <div className="success">KYC submitted successfully!</div>}

      {/* Personal Information */}
      <div className="form-section">
        <h3>Personal Information</h3>
        
        <input
          type="text"
          placeholder="Full Name"
          value={formData.name}
          onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
          required
        />

        <input
          type="tel"
          placeholder="Phone Number (11 digits)"
          value={formData.phone}
          onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
          pattern="[0-9]{11}"
          required
        />

        <input
          type="email"
          placeholder="Email Address"
          value={formData.email}
          onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
          required
        />

        <input
          type="date"
          placeholder="Date of Birth"
          value={formData.date_of_birth}
          onChange={(e) => setFormData(prev => ({ ...prev, date_of_birth: e.target.value }))}
          required
        />

        <select
          value={formData.gender}
          onChange={(e) => setFormData(prev => ({ ...prev, gender: e.target.value }))}
          required
        >
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </select>
      </div>

      {/* Address */}
      <div className="form-section">
        <h3>Address</h3>
        <textarea
          placeholder="Full Address"
          value={formData.address}
          onChange={(e) => setFormData(prev => ({ ...prev, address: e.target.value }))}
          required
        />
      </div>

      {/* National ID Information */}
      <div className="form-section">
        <h3>National ID Information</h3>
        
        <input
          type="text"
          placeholder="National ID Number"
          value={formData.national_id_number}
          onChange={(e) => setFormData(prev => ({ ...prev, national_id_number: e.target.value }))}
          required
        />

        <input
          type="date"
          placeholder="National ID Expiry Date"
          value={formData.national_id_expiry}
          onChange={(e) => setFormData(prev => ({ ...prev, national_id_expiry: e.target.value }))}
          required
        />
      </div>

      {/* Document Uploads */}
      <div className="form-section">
        <h3>Required Documents</h3>
        
        <div className="file-upload">
          <label>National ID Front</label>
          <input
            type="file"
            accept="image/*"
            onChange={(e) => handleFileChange('national_id_front', e.target.files[0])}
            required
          />
        </div>

        <div className="file-upload">
          <label>National ID Back</label>
          <input
            type="file"
            accept="image/*"
            onChange={(e) => handleFileChange('national_id_back', e.target.files[0])}
            required
          />
        </div>

        <div className="file-upload">
          <label>Selfie</label>
          <input
            type="file"
            accept="image/*"
            onChange={(e) => handleFileChange('selfie', e.target.files[0])}
            required
          />
        </div>

        <div className="file-upload">
          <label>Utility Bill (Optional)</label>
          <input
            type="file"
            accept="image/*,.pdf"
            onChange={(e) => handleFileChange('utility_bill', e.target.files[0])}
          />
        </div>
      </div>

      <button type="submit" disabled={loading}>
        {loading ? 'Submitting...' : 'Submit KYC Application'}
      </button>
    </form>
  );
};

// 2. OTP Verification Component
export const OTPVerification = () => {
  const [phone, setPhone] = useState('');
  const [otp, setOtp] = useState('');
  const [loading, setLoading] = useState(false);
  const [otpSent, setOtpSent] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const handleSendOtp = async () => {
    if (!phone) {
      setError('Please enter your phone number');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await kycAPI.sendOtp(phone);
      setOtpSent(true);
      setSuccess('OTP sent successfully!');
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOtp = async () => {
    if (!otp) {
      setError('Please enter the OTP');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await kycAPI.verifyOtp(phone, otp);
      setSuccess('Phone number verified successfully!');
      // Redirect to status check
      // navigate('/kyc/status');
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="otp-verification">
      <h2>Phone Verification</h2>
      
      {error && <div className="error">{error}</div>}
      {success && <div className="success">{success}</div>}

      <div className="form-group">
        <input
          type="tel"
          placeholder="Phone Number"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          disabled={otpSent}
        />
        
        {!otpSent && (
          <button onClick={handleSendOtp} disabled={loading}>
            {loading ? 'Sending...' : 'Send OTP'}
          </button>
        )}
      </div>

      {otpSent && (
        <div className="form-group">
          <input
            type="text"
            placeholder="Enter 6-digit OTP"
            value={otp}
            onChange={(e) => setOtp(e.target.value)}
            maxLength={6}
          />
          
          <button onClick={handleVerifyOtp} disabled={loading}>
            {loading ? 'Verifying...' : 'Verify OTP'}
          </button>
        </div>
      )}
    </div>
  );
};

// 3. KYC Status Check Component
export const KYCStatusCheck = () => {
  const [phone, setPhone] = useState('');
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const checkStatus = async () => {
    if (!phone) {
      setError('Please enter your phone number');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await kycAPI.checkStatus(phone);
      setStatus(response.data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="kyc-status">
      <h2>Check KYC Status</h2>
      
      {error && <div className="error">{error}</div>}

      <div className="form-group">
        <input
          type="tel"
          placeholder="Enter your phone number"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
        />
        
        <button onClick={checkStatus} disabled={loading}>
          {loading ? 'Checking...' : 'Check Status'}
        </button>
      </div>

      {status && (
        <div className="status-result">
          <h3>KYC Status: {status.kyc_status}</h3>
          <p><strong>Name:</strong> {status.name}</p>
          <p><strong>Phone:</strong> {status.phone}</p>
          <p><strong>Phone Verified:</strong> {status.phone_verified ? 'Yes' : 'No'}</p>
          
          {status.submitted_at && (
            <p><strong>Submitted:</strong> {new Date(status.submitted_at).toLocaleDateString()}</p>
          )}
          
          {status.approved_at && (
            <p><strong>Approved:</strong> {new Date(status.approved_at).toLocaleDateString()}</p>
          )}
          
          {status.rejection_reason && (
            <p><strong>Rejection Reason:</strong> {status.rejection_reason}</p>
          )}
        </div>
      )}
    </div>
  );
};

// 4. User Dashboard Component (Authenticated)
export const KYCDashboard = () => {
  const [userStatus, setUserStatus] = useState(null);
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadUserData();
  }, []);

  const loadUserData = async () => {
    try {
      const [statusResponse, documentsResponse] = await Promise.all([
        kycAPI.getMyStatus(),
        kycAPI.getMyDocuments(),
      ]);

      setUserStatus(statusResponse.data);
      setDocuments(documentsResponse.data.documents);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading...</div>;
  if (error) return <div className="error">{error}</div>;

  return (
    <div className="kyc-dashboard">
      <h2>My KYC Dashboard</h2>
      
      {/* Status Section */}
      <div className="status-section">
        <h3>KYC Status: {userStatus.kyc_status}</h3>
        <p><strong>Name:</strong> {userStatus.name}</p>
        <p><strong>Phone:</strong> {userStatus.phone}</p>
        <p><strong>Phone Verified:</strong> {userStatus.phone_verified ? 'Yes' : 'No'}</p>
      </div>

      {/* Documents Section */}
      <div className="documents-section">
        <h3>My Documents</h3>
        {documents.length === 0 ? (
          <p>No documents uploaded yet.</p>
        ) : (
          <div className="documents-list">
            {documents.map(doc => (
              <div key={doc.id} className="document-item">
                <span className="document-type">{doc.document_type}</span>
                <span className={`status status-${doc.status}`}>{doc.status}</span>
                <span className="date">{new Date(doc.submitted_at).toLocaleDateString()}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

// 5. Admin KYC Management Component
export const AdminKYCManagement = () => {
  const [pendingApplications, setPendingApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadPendingApplications();
  }, []);

  const loadPendingApplications = async () => {
    try {
      const response = await apiCall('/admin/kyc-pending');
      setPendingApplications(response.data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const approveKYC = async (userId) => {
    try {
      await apiCall(`/admin/kyc/${userId}/approve`, {
        method: 'POST',
      });
      loadPendingApplications(); // Reload list
    } catch (error) {
      setError(error.message);
    }
  };

  const rejectKYC = async (userId, reason) => {
    try {
      await apiCall(`/admin/kyc/${userId}/reject`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      });
      loadPendingApplications(); // Reload list
    } catch (error) {
      setError(error.message);
    }
  };

  if (loading) return <div>Loading...</div>;
  if (error) return <div className="error">{error}</div>;

  return (
    <div className="admin-kyc">
      <h2>KYC Applications Management</h2>
      
      {pendingApplications.length === 0 ? (
        <p>No pending KYC applications.</p>
      ) : (
        <div className="applications-list">
          {pendingApplications.map(app => (
            <div key={app.id} className="application-item">
              <h4>{app.name}</h4>
              <p><strong>Phone:</strong> {app.phone}</p>
              <p><strong>Email:</strong> {app.email}</p>
              <p><strong>Submitted:</strong> {new Date(app.created_at).toLocaleDateString()}</p>
              
              <div className="actions">
                <button onClick={() => approveKYC(app.id)} className="approve-btn">
                  Approve
                </button>
                <button onClick={() => rejectKYC(app.id, 'Document verification failed')} className="reject-btn">
                  Reject
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

// Usage Example:
// 1. KYC Submission: <KYCSubmissionForm />
// 2. OTP Verification: <OTPVerification />
// 3. Status Check: <KYCStatusCheck />
// 4. User Dashboard: <KYCDashboard />
// 5. Admin Management: <AdminKYCManagement /> 