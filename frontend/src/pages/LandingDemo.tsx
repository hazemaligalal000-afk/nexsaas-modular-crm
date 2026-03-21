/**
 * Landing Page Demo
 * Standalone page to preview the landing page
 */

import React from 'react';
import { LandingPage } from './Landing';
import '../i18n/config';

export const LandingDemo: React.FC = () => {
  return <LandingPage />;
};

export default LandingDemo;
