/**
 * Landing Page Component Tests
 * Basic rendering tests for the landing page
 */

import React from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LandingPage } from './LandingPage';

// Mock i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
    i18n: {
      language: 'en-US',
      changeLanguage: vi.fn(),
    },
  }),
}));

describe('LandingPage', () => {
  it('renders without crashing', () => {
    render(<LandingPage />);
    expect(document.querySelector('.landing-page')).toBeInTheDocument();
  });

  it('renders hero section', () => {
    render(<LandingPage />);
    expect(document.querySelector('.hero-section')).toBeInTheDocument();
  });
});
