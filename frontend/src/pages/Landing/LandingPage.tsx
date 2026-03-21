/**
 * Landing Page Component
 * Public-facing marketing website
 * Requirements: 1, 6
 */

import React from 'react';
import { HeroSection } from './HeroSection';
import './LandingPage.css';

export const LandingPage: React.FC = () => {
  return (
    <div className="landing-page">
      <HeroSection />
      {/* Additional sections will be added in subsequent tasks */}
    </div>
  );
};
