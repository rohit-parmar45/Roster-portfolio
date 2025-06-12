import { useState } from 'react';
import { Globe, User, Briefcase, Play, ExternalLink, Loader2, AlertCircle, CheckCircle } from 'lucide-react';
import '../components/PortfolioFeature.css';

const PortfolioFeature = () => {
  const [portfolioUrl, setPortfolioUrl] = useState('');
  const [portfolioData, setPortfolioData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);

  const handleSubmit = async () => {
    if (!portfolioUrl.trim()) {
      setError('Please enter a valid portfolio URL');
      return;
    }

    setLoading(true);
    setError('');
    setSuccess(false);

    try {
      const response = await fetch('http://localhost:8000/api/portfolios', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url: portfolioUrl }),
      });

      const result = await response.json();

      if (result.success) {
        // Transform backend data to match frontend structure
        setPortfolioData({
          basicInfo: {
            name: result.data.name,
            title: result.data.title,
            bio: result.data.bio,
            email: result.data.email,
            location: result.data.location,
            website: result.data.website,
          },
          employers: result.data.employers.map(emp => ({
            id: emp.id,
            name: emp.name,
            role: emp.role,
            period: emp.period,
            description: emp.description,
            videos: emp.videos || [],
          })),
        });
        setSuccess(true);
      } else {
        setError(result.message || 'Failed to process portfolio');
        setPortfolioData(null);
      }
    } catch (err) {
      setError('Failed to connect to server. Please try again.');
      setPortfolioData(null);
    } finally {
      setLoading(false);
    }
  };

  const VideoCard = ({ video }) => (
    <div className="video-card">
      <div className="video-thumbnail">
        <img 
          src={video.thumbnail || `https://via.placeholder.com/300x200/1f1f1f/ffffff?text=${encodeURIComponent(video.title)}`} 
          alt={video.title}
        />
        <div className="play-overlay">
          <Play className="play-icon" />
        </div>
        {video.duration && (
          <div className="video-duration">
            {video.duration}
          </div>
        )}
      </div>
      <div className="video-info">
        <h4 className="video-title">{video.title}</h4>
        <a 
          href={video.url} 
          target="_blank" 
          rel="noopener noreferrer"
          className="video-link"
        >
          Watch Video
          <ExternalLink className="external-icon" />
        </a>
      </div>
    </div>
  );

  return (
    <div className="portfolio-container">
      <div className="portfolio-wrapper">
        <div className="header">
          <h1 className="main-title">Portfolio Integration</h1>
          <p className="main-subtitle">Add your portfolio website to showcase your work</p>
        </div>

        {/* Portfolio URL Submission Form */}
        <div className="form-section">
          <div className="section-header">
            <Globe className="section-icon" />
            <h2 className="section-title">Add Portfolio Website</h2>
          </div>
          
          <div className="form-content">
            <div className="input-group">
              <label htmlFor="portfolioUrl" className="input-label">
                Portfolio URL
              </label>
              <input
                type="url"
                id="portfolioUrl"
                value={portfolioUrl}
                onChange={(e) => setPortfolioUrl(e.target.value)}
                placeholder="https://your-portfolio.com"
                className="url-input"
                disabled={loading}
              />
            </div>
            
            {error && (
              <div className="error-message">
                <AlertCircle className="message-icon" />
                {error}
              </div>
            )}
            
            {success && (
              <div className="success-message">
                <CheckCircle className="message-icon" />
                Portfolio data retrieved successfully!
              </div>
            )}
            
            <button
              onClick={handleSubmit}
              disabled={loading}
              className={`submit-button ${loading ? 'loading' : ''}`}
            >
              {loading ? (
                <>
                  <Loader2 className="spinner" />
                  Retrieving Portfolio Data...
                </>
              ) : (
                'Add Portfolio'
              )}
            </button>
          </div>
        </div>

        {/* Portfolio Display */}
        {portfolioData && (
          <div className="portfolio-display">
            {/* Basic Info Section */}
            <div className="info-section">
              <div className="section-header">
                <User className="section-icon" />
                <h2 className="section-title">Basic Info</h2>
              </div>
              
              <div className="basic-info-content">
                <div className="info-main">
                  <h3 className="person-name">
                    {portfolioData.basicInfo.name}
                  </h3>
                  <p className="person-title">
                    {portfolioData.basicInfo.title}
                  </p>
                  <p className="person-bio">
                    {portfolioData.basicInfo.bio}
                  </p>
                </div>
                
                <div className="info-details">
                  {portfolioData.basicInfo.email && (
                    <div className="detail-item">
                      <span className="detail-label">Email:</span>
                      <span className="detail-value">{portfolioData.basicInfo.email}</span>
                    </div>
                  )}
                  {portfolioData.basicInfo.location && (
                    <div className="detail-item">
                      <span className="detail-label">Location:</span>
                      <span className="detail-value">{portfolioData.basicInfo.location}</span>
                    </div>
                  )}
                  <div className="detail-item">
                    <span className="detail-label">Website:</span>
                    <a 
                      href={portfolioData.basicInfo.website}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="website-link"
                    >
                      Visit Portfolio
                      <ExternalLink className="external-icon" />
                    </a>
                  </div>
                </div>
              </div>
            </div>

            {/* Employers/Clients Section */}
            <div className="employers-section">
              <div className="section-header">
                <Briefcase className="section-icon" />
                <h2 className="section-title">Employers/Clients</h2>
              </div>
              
              <div className="employers-list">
                {portfolioData.employers.map((employer) => (
                  <div key={employer.id} className="employer-item">
                    <div className="employer-info">
                      <h3 className="employer-name">
                        {employer.name}
                      </h3>
                      {employer.role && (
                        <p className="employer-role">
                          {employer.role}
                        </p>
                      )}
                      {employer.period && (
                        <p className="employer-period">
                          {employer.period}
                        </p>
                      )}
                      {employer.description && (
                        <p className="employer-description">
                          {employer.description}
                        </p>
                      )}
                    </div>
                    
                    {employer.videos && employer.videos.length > 0 && (
                      <div className="videos-section">
                        <h4 className="videos-title">Related Videos</h4>
                        <div className="videos-grid">
                          {employer.videos.map((video) => (
                            <VideoCard key={video.id} video={video} />
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default PortfolioFeature;