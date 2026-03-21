/**
 * Hero Section Component
 * Above-the-fold section with headline, CTA buttons, and hero image
 * Requirements: 1, 6
 */

import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { VideoModal } from '../../components/VideoModal/VideoModal';
import './HeroSection.css';

export const HeroSection: React.FC = () => {
  const { t } = useTranslation();
  const [showVideoModal, setShowVideoModal] = useState(false);

  return (
    <section className="hero-section">
      <div className="hero-container">
        <div className="hero-content">
          <h1 className="hero-headline">{t('landing.hero.headline')}</h1>
          <p className="hero-subheadline">{t('landing.hero.subheadline')}</p>
          
          <div className="hero-cta-buttons">
            <a 
              href="/signup" 
              className="btn btn-primary btn-large"
            >
              {t('landing.hero.ctaTrial')}
            </a>
            
            <button 
              onClick={() => setShowVideoModal(true)}
              className="btn btn-secondary btn-large"
            >
              <span className="btn-icon">▶</span>
              {t('landing.hero.ctaDemo')}
            </button>
          </div>
        </div>
        
        <div className="hero-media">
          <picture>
            <source 
              srcSet="/images/hero-dashboard.webp" 
              type="image/webp" 
            />
            <source 
              srcSet="/images/hero-dashboard.png" 
              type="image/png" 
            />
            <img 
              src="/images/hero-dashboard.svg" 
              alt={t('landing.hero.imageAlt')}
              className="hero-image"
              loading="eager"
            />
          </picture>
        </div>
      </div>
      
      {showVideoModal && (
        <VideoModal 
          videoId="demo-video"
          onClose={() => setShowVideoModal(false)}
        />
      )}
    </section>
  );
};
