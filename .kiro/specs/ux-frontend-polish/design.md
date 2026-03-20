# Design Document: UX & Frontend Polish

## Overview

This design document specifies the architecture and implementation for Phase 3 of the NexSaaS AI Revenue Operating System: UX & Frontend Polish. This phase transforms the functional platform into a visually competitive, market-ready SaaS product through three major initiatives:

1. **Landing Page**: A compelling public-facing marketing website with hero section, features showcase, pricing table, social proof, FAQ, demo video, and live demo environment
2. **Arabic/RTL Support**: Complete internationalization with react-i18next, 100% UI string translation, RTL layout using CSS logical properties, and Arabic number/date formatting
3. **Dashboard UX Refresh**: Modern dashboard with KPIs, interactive charts (Recharts), leads list with real-time search, virtual scrolling, mobile responsive design, loading skeletons, and dark mode

This phase is critical for customer perception, adoption, and competitive positioning in the MENA market. The implementation focuses entirely on frontend experience without requiring backend architectural changes.

### Key Design Goals

1. **Market Readiness**: Create a professional, polished interface that competes with established SaaS products
2. **MENA Market Focus**: Full Arabic language support with culturally appropriate RTL layout and formatting
3. **Performance**: Sub-2-second page loads, 60fps interactions, virtual scrolling for large datasets
4. **Accessibility**: WCAG 2.1 Level AA compliance with keyboard navigation and screen reader support
5. **Mobile Experience**: Responsive design for top 5 pages with touch-friendly controls
6. **Developer Experience**: Clean component architecture, reusable patterns, comprehensive i18n coverage

### Technology Stack

- **Frontend Framework**: React 18+ with TypeScript
- **Internationalization**: react-i18next with JSON translation files
- **Charts**: Recharts (React-based, responsive, RTL-compatible)
- **Virtual Scrolling**: react-window for large lists
- **Styling**: CSS Modules with logical properties for RTL support
- **Animation**: framer-motion for smooth transitions
- **Form Handling**: React Hook Form with validation
- **Date/Number Formatting**: Intl.NumberFormat and Intl.DateTimeFormat APIs
- **Video Hosting**: Vimeo Pro with subtitle support
- **Chat Widget**: Crisp (lightweight, multilingual)


## Architecture

### High-Level System Architecture

```mermaid
graph TB
    subgraph "Public Website"
        Landing[Landing Page]
        Demo[Demo Environment]
        Contact[Contact Form]
    end
    
    subgraph "Application Frontend"
        Dashboard[Dashboard]
        Leads[Leads List]
        Inbox[Inbox]
        Settings[Settings]
    end
    
    subgraph "i18n Layer"
        i18next[react-i18next]
        Translations[Translation Files]
        Formatter[Intl Formatters]
    end
    
    subgraph "UI Components"
        Charts[Recharts]
        VirtualList[react-window]
        Skeleton[Loading Skeletons]
        DarkMode[Theme Provider]
    end
    
    subgraph "External Services"
        Vimeo[Vimeo Video]
        Crisp[Crisp Chat]
        Backend[PHP Backend API]
    end
    
    Landing --> Demo
    Landing --> Contact
    Landing --> Vimeo
    Landing --> Crisp
    
    Dashboard --> Charts
    Dashboard --> i18next
    Dashboard --> DarkMode
    
    Leads --> VirtualList
    Leads --> i18next
    Leads --> Skeleton
    
    i18next --> Translations
    i18next --> Formatter
    
    Contact --> Backend
    Demo --> Backend
    Dashboard --> Backend
    Leads --> Backend
    
    style "i18n Layer" fill:#e1f5ff
    style "UI Components" fill:#fff4e1
    style "External Services" fill:#ffe1f5
```

### Component Architecture

**Landing Page Structure:**
```
LandingPage/
├── HeroSection/
│   ├── Headline
│   ├── Subheadline
│   ├── CTAButtons
│   └── HeroMedia (image/video)
├── FeaturesShowcase/
│   └── FeatureCard[] (6 features)
├── PricingTable/
│   └── PricingTier[] (3 tiers)
├── SocialProof/
│   ├── TestimonialCarousel
│   ├── ClientLogos
│   └── MetricsBanner
├── FAQ/
│   └── AccordionItem[]
├── DemoVideo/
│   └── VideoModal
└── ContactSection/
    ├── ContactForm
    └── ChatWidget
```

**Dashboard Architecture:**
```
Dashboard/
├── DateRangeFilter
├── KPIGrid/
│   └── KPICard[] (6 metrics)
│       ├── Value
│       ├── TrendIndicator
│       └── PercentageChange
├── ChartsGrid/
│   ├── BarChart (Deals by Stage)
│   ├── LineChart (Lead Capture Trend)
│   ├── PieChart (AI Score Distribution)
│   └── AreaChart (Revenue Pipeline)
└── LoadingSkeleton
```

**i18n Architecture:**
```
i18n/
├── config.ts (react-i18next setup)
├── locales/
│   ├── en-US.json
│   └── ar-SA.json
├── hooks/
│   ├── useTranslation.ts
│   └── useLocale.ts
├── formatters/
│   ├── numberFormatter.ts
│   ├── dateFormatter.ts
│   └── currencyFormatter.ts
└── LanguageSwitcher/
    └── LanguageDropdown
```

### RTL Layout Strategy

**CSS Logical Properties Mapping:**
```css
/* Instead of: */
margin-left: 16px;
padding-right: 24px;
text-align: left;
border-left: 1px solid;

/* Use: */
margin-inline-start: 16px;
padding-inline-end: 24px;
text-align: start;
border-inline-start: 1px solid;
```

**Direction Detection:**
```typescript
// Automatically apply direction based on locale
const direction = locale === 'ar-SA' ? 'rtl' : 'ltr';
document.documentElement.setAttribute('dir', direction);
```

**Component Mirroring:**
- Navigation menus: flip horizontal order
- Sidebars: move from left to right
- Form layouts: labels on right, inputs on left
- Button groups: reverse order
- Icons: flip only directional arrows (→ becomes ←)
- Charts: Y-axis on right, X-axis right-to-left



## Components and Interfaces

### 1. Landing Page Components

#### 1.1 Hero Section

**Component Structure:**
```typescript
// frontend/src/pages/Landing/HeroSection.tsx
interface HeroSectionProps {
  locale: 'en-US' | 'ar-SA';
}

export const HeroSection: React.FC<HeroSectionProps> = ({ locale }) => {
  const { t } = useTranslation();
  const [showVideoModal, setShowVideoModal] = useState(false);

  return (
    <section className="hero-section">
      <div className="hero-content">
        <h1 className="headline">{t('landing.hero.headline')}</h1>
        <p className="subheadline">{t('landing.hero.subheadline')}</p>
        
        <div className="cta-buttons">
          <Button 
            variant="primary" 
            href="/signup"
            size="large"
          >
            {t('landing.hero.cta_trial')}
          </Button>
          
          <Button 
            variant="secondary" 
            onClick={() => setShowVideoModal(true)}
            size="large"
          >
            {t('landing.hero.cta_demo')}
          </Button>
        </div>
      </div>
      
      <div className="hero-media">
        <img 
          src="/images/hero-dashboard.png" 
          alt={t('landing.hero.image_alt')}
          loading="eager"
        />
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
```

**Translation Keys:**
```json
{
  "landing.hero.headline": "AI-Powered Revenue Operating System",
  "landing.hero.subheadline": "Close more deals with intelligent lead scoring, omnichannel inbox, and automated workflows",
  "landing.hero.cta_trial": "Start Free Trial",
  "landing.hero.cta_demo": "Watch Demo",
  "landing.hero.image_alt": "NexSaaS Dashboard Preview"
}
```

**Performance Optimization:**
- Hero image: WebP format with fallback, max 200KB
- Above-the-fold CSS: inline critical styles
- Preload hero image: `<link rel="preload" as="image" href="/images/hero-dashboard.webp">`
- Lazy load below-the-fold content

#### 1.2 Features Showcase

**Component Structure:**
```typescript
// frontend/src/pages/Landing/FeaturesShowcase.tsx
interface Feature {
  icon: string;
  titleKey: string;
  descriptionKey: string;
  screenshot?: string;
}

const FEATURES: Feature[] = [
  {
    icon: 'brain',
    titleKey: 'landing.features.ai_scoring.title',
    descriptionKey: 'landing.features.ai_scoring.description',
    screenshot: '/images/features/ai-scoring.png'
  },
  {
    icon: 'inbox',
    titleKey: 'landing.features.omnichannel.title',
    descriptionKey: 'landing.features.omnichannel.description',
    screenshot: '/images/features/inbox.png'
  },
  {
    icon: 'pipeline',
    titleKey: 'landing.features.pipeline.title',
    descriptionKey: 'landing.features.pipeline.description',
    screenshot: '/images/features/pipeline.png'
  },
  {
    icon: 'language',
    titleKey: 'landing.features.arabic.title',
    descriptionKey: 'landing.features.arabic.description',
    screenshot: '/images/features/rtl.png'
  },
  {
    icon: 'automation',
    titleKey: 'landing.features.automation.title',
    descriptionKey: 'landing.features.automation.description',
    screenshot: '/images/features/workflows.png'
  },
  {
    icon: 'analytics',
    titleKey: 'landing.features.analytics.title',
    descriptionKey: 'landing.features.analytics.description',
    screenshot: '/images/features/dashboard.png'
  }
];

export const FeaturesShowcase: React.FC = () => {
  const { t } = useTranslation();

  return (
    <section className="features-showcase">
      <h2>{t('landing.features.section_title')}</h2>
      
      <div className="features-grid">
        {FEATURES.map((feature, index) => (
          <FeatureCard
            key={index}
            icon={feature.icon}
            title={t(feature.titleKey)}
            description={t(feature.descriptionKey)}
            screenshot={feature.screenshot}
          />
        ))}
      </div>
    </section>
  );
};
```

**Responsive Grid:**
```css
.features-grid {
  display: grid;
  gap: 2rem;
  
  /* Mobile: 1 column */
  grid-template-columns: 1fr;
  
  /* Tablet: 2 columns */
  @media (min-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
  
  /* Desktop: 3 columns */
  @media (min-width: 1024px) {
    grid-template-columns: repeat(3, 1fr);
  }
}
```

#### 1.3 Pricing Table

**Component Structure:**
```typescript
// frontend/src/pages/Landing/PricingTable.tsx
interface PricingTier {
  id: 'starter' | 'growth' | 'enterprise';
  nameKey: string;
  price: number;
  currency: string;
  features: string[];
  highlighted?: boolean;
  ctaKey: string;
  ctaLink: string;
}

const PRICING_TIERS: PricingTier[] = [
  {
    id: 'starter',
    nameKey: 'landing.pricing.starter.name',
    price: 49,
    currency: 'USD',
    features: [
      'landing.pricing.starter.users',
      'landing.pricing.starter.ai_requests',
      'landing.pricing.starter.storage',
      'landing.pricing.starter.features'
    ],
    ctaKey: 'landing.pricing.cta_trial',
    ctaLink: '/signup?tier=starter'
  },
  {
    id: 'growth',
    nameKey: 'landing.pricing.growth.name',
    price: 149,
    currency: 'USD',
    features: [
      'landing.pricing.growth.users',
      'landing.pricing.growth.ai_requests',
      'landing.pricing.growth.storage',
      'landing.pricing.growth.features'
    ],
    highlighted: true,
    ctaKey: 'landing.pricing.cta_trial',
    ctaLink: '/signup?tier=growth'
  },
  {
    id: 'enterprise',
    nameKey: 'landing.pricing.enterprise.name',
    price: 499,
    currency: 'USD',
    features: [
      'landing.pricing.enterprise.users',
      'landing.pricing.enterprise.ai_requests',
      'landing.pricing.enterprise.storage',
      'landing.pricing.enterprise.features'
    ],
    ctaKey: 'landing.pricing.cta_contact',
    ctaLink: '/contact-sales'
  }
];

export const PricingTable: React.FC = () => {
  const { t } = useTranslation();
  const { formatCurrency } = useCurrencyFormatter();

  return (
    <section className="pricing-table">
      <h2>{t('landing.pricing.section_title')}</h2>
      
      <div className="pricing-grid">
        {PRICING_TIERS.map((tier) => (
          <PricingCard
            key={tier.id}
            name={t(tier.nameKey)}
            price={formatCurrency(tier.price, tier.currency)}
            features={tier.features.map(key => t(key))}
            highlighted={tier.highlighted}
            ctaText={t(tier.ctaKey)}
            ctaLink={tier.ctaLink}
          />
        ))}
      </div>
      
      <p className="pricing-note">
        {t('landing.pricing.vat_note')}
      </p>
    </section>
  );
};
```

**Mobile Responsive:**
```css
.pricing-grid {
  display: grid;
  gap: 2rem;
  
  /* Mobile: horizontal scroll */
  @media (max-width: 767px) {
    grid-auto-flow: column;
    grid-auto-columns: 280px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
  }
  
  /* Tablet/Desktop: full grid */
  @media (min-width: 768px) {
    grid-template-columns: repeat(3, 1fr);
  }
}
```

#### 1.4 Social Proof Section

**Component Structure:**
```typescript
// frontend/src/pages/Landing/SocialProof.tsx
interface Testimonial {
  name: string;
  title: string;
  company: string;
  photo: string;
  quoteKey: string;
}

const TESTIMONIALS: Testimonial[] = [
  {
    name: 'Ahmed Al-Rashid',
    title: 'Sales Director',
    company: 'TechCorp MENA',
    photo: '/images/testimonials/ahmed.jpg',
    quoteKey: 'landing.testimonials.ahmed.quote'
  },
  {
    name: 'Sarah Johnson',
    title: 'VP of Sales',
    company: 'Global Solutions',
    photo: '/images/testimonials/sarah.jpg',
    quoteKey: 'landing.testimonials.sarah.quote'
  },
  {
    name: 'Mohammed Hassan',
    title: 'CEO',
    company: 'StartupHub',
    photo: '/images/testimonials/mohammed.jpg',
    quoteKey: 'landing.testimonials.mohammed.quote'
  }
];

const METRICS = [
  { valueKey: 'landing.metrics.companies', value: 500 },
  { valueKey: 'landing.metrics.leads', value: 50000 },
  { valueKey: 'landing.metrics.satisfaction', value: 95 }
];

export const SocialProof: React.FC = () => {
  const { t } = useTranslation();
  const [currentTestimonial, setCurrentTestimonial] = useState(0);

  return (
    <section className="social-proof">
      <h2>{t('landing.social_proof.section_title')}</h2>
      
      {/* Testimonial Carousel */}
      <div className="testimonial-carousel">
        <TestimonialCard testimonial={TESTIMONIALS[currentTestimonial]} />
        
        <div className="carousel-controls">
          {TESTIMONIALS.map((_, index) => (
            <button
              key={index}
              className={index === currentTestimonial ? 'active' : ''}
              onClick={() => setCurrentTestimonial(index)}
              aria-label={`View testimonial ${index + 1}`}
            />
          ))}
        </div>
      </div>
      
      {/* Metrics Banner */}
      <div className="metrics-banner">
        {METRICS.map((metric, index) => (
          <AnimatedMetric
            key={index}
            label={t(metric.valueKey)}
            value={metric.value}
          />
        ))}
      </div>
      
      {/* Client Logos */}
      <div className="client-logos">
        <h3>{t('landing.social_proof.trusted_by')}</h3>
        <div className="logos-grid">
          {/* Logo images */}
        </div>
      </div>
    </section>
  );
};
```

**Animated Metric Component:**
```typescript
// frontend/src/components/AnimatedMetric.tsx
import { useInView } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';

interface AnimatedMetricProps {
  label: string;
  value: number;
  suffix?: string;
}

export const AnimatedMetric: React.FC<AnimatedMetricProps> = ({ 
  label, 
  value, 
  suffix = '' 
}) => {
  const ref = useRef(null);
  const isInView = useInView(ref, { once: true });
  const [count, setCount] = useState(0);

  useEffect(() => {
    if (isInView) {
      const duration = 2000; // 2 seconds
      const steps = 60;
      const increment = value / steps;
      let current = 0;

      const timer = setInterval(() => {
        current += increment;
        if (current >= value) {
          setCount(value);
          clearInterval(timer);
        } else {
          setCount(Math.floor(current));
        }
      }, duration / steps);

      return () => clearInterval(timer);
    }
  }, [isInView, value]);

  return (
    <div ref={ref} className="animated-metric">
      <div className="metric-value">
        {count.toLocaleString()}{suffix}
      </div>
      <div className="metric-label">{label}</div>
    </div>
  );
};
```

#### 1.5 FAQ Section

**Component Structure:**
```typescript
// frontend/src/pages/Landing/FAQ.tsx
interface FAQItem {
  questionKey: string;
  answerKey: string;
}

const FAQ_ITEMS: FAQItem[] = [
  {
    questionKey: 'landing.faq.pricing.question',
    answerKey: 'landing.faq.pricing.answer'
  },
  {
    questionKey: 'landing.faq.trial.question',
    answerKey: 'landing.faq.trial.answer'
  },
  {
    questionKey: 'landing.faq.security.question',
    answerKey: 'landing.faq.security.answer'
  },
  {
    questionKey: 'landing.faq.arabic.question',
    answerKey: 'landing.faq.arabic.answer'
  },
  {
    questionKey: 'landing.faq.integrations.question',
    answerKey: 'landing.faq.integrations.answer'
  },
  {
    questionKey: 'landing.faq.migration.question',
    answerKey: 'landing.faq.migration.answer'
  },
  {
    questionKey: 'landing.faq.cancellation.question',
    answerKey: 'landing.faq.cancellation.answer'
  },
  {
    questionKey: 'landing.faq.support.question',
    answerKey: 'landing.faq.support.answer'
  }
];

export const FAQ: React.FC = () => {
  const { t } = useTranslation();
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  const toggleItem = (index: number) => {
    setOpenIndex(openIndex === index ? null : index);
  };

  return (
    <section className="faq">
      <h2>{t('landing.faq.section_title')}</h2>
      
      <div className="faq-list">
        {FAQ_ITEMS.map((item, index) => (
          <div 
            key={index} 
            className={`faq-item ${openIndex === index ? 'open' : ''}`}
          >
            <button
              className="faq-question"
              onClick={() => toggleItem(index)}
              aria-expanded={openIndex === index}
            >
              <span>{t(item.questionKey)}</span>
              <ChevronIcon direction={openIndex === index ? 'up' : 'down'} />
            </button>
            
            {openIndex === index && (
              <div className="faq-answer">
                {t(item.answerKey)}
              </div>
            )}
          </div>
        ))}
      </div>
      
      <div className="faq-cta">
        <p>{t('landing.faq.still_questions')}</p>
        <Button href="/contact">{t('landing.faq.contact_us')}</Button>
      </div>
    </section>
  );
};
```

#### 1.6 Demo Video Modal

**Component Structure:**
```typescript
// frontend/src/components/VideoModal.tsx
interface VideoModalProps {
  videoId: string;
  onClose: () => void;
}

export const VideoModal: React.FC<VideoModalProps> = ({ videoId, onClose }) => {
  const modalRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };

    const handleClickOutside = (e: MouseEvent) => {
      if (modalRef.current && !modalRef.current.contains(e.target as Node)) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    document.addEventListener('mousedown', handleClickOutside);

    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [onClose]);

  return (
    <div className="video-modal-overlay">
      <div ref={modalRef} className="video-modal-content">
        <button className="close-button" onClick={onClose} aria-label="Close">
          ×
        </button>
        
        <iframe
          src={`https://player.vimeo.com/video/${videoId}?h=abc123&title=0&byline=0&portrait=0`}
          width="640"
          height="360"
          frameBorder="0"
          allow="autoplay; fullscreen"
          allowFullScreen
          title="Product Demo Video"
        />
      </div>
    </div>
  );
};
```

**Vimeo Configuration:**
- Video duration: 3-5 minutes
- Subtitles: Arabic and English tracks
- Privacy: Hide from Vimeo.com
- Autoplay: Disabled (user-initiated)
- Controls: Enabled

#### 1.7 Demo Environment

**Component Structure:**
```typescript
// frontend/src/pages/DemoEnvironment.tsx
export const DemoEnvironment: React.FC = () => {
  const { t } = useTranslation();
  const [showUpgradeModal, setShowUpgradeModal] = useState(false);

  // Intercept write operations
  const handleWriteOperation = (operation: string) => {
    setShowUpgradeModal(true);
    return false; // Prevent operation
  };

  return (
    <div className="demo-environment">
      {/* Persistent banner */}
      <div className="demo-banner">
        <InfoIcon />
        <span>{t('demo.banner_message')}</span>
        <Button size="small" href="/signup">
          {t('demo.banner_cta')}
        </Button>
      </div>
      
      {/* Regular app interface with read-only mode */}
      <AppLayout 
        readOnly={true}
        onWriteAttempt={handleWriteOperation}
      />
      
      {/* Upgrade modal */}
      {showUpgradeModal && (
        <Modal onClose={() => setShowUpgradeModal(false)}>
          <h3>{t('demo.upgrade_modal.title')}</h3>
          <p>{t('demo.upgrade_modal.message')}</p>
          <Button href="/signup" variant="primary">
            {t('demo.upgrade_modal.cta')}
          </Button>
        </Modal>
      )}
    </div>
  );
};
```

**Sample Data Generation:**
```typescript
// backend: modular_core/cli/seed_demo_tenant.php
// Generates sample data for demo environment:
// - 50 leads with varied scores (20-95)
// - 20 deals across 3 stages (Qualified, Proposal, Negotiation)
// - 30 contacts with complete profiles
// - 15 activities (calls, meetings, emails)
// - 10 inbox messages (WhatsApp, Email)

// Reset script runs daily at 00:00 UTC via cron
```

#### 1.8 Contact Form and Chat Widget

**Contact Form Component:**
```typescript
// frontend/src/pages/Landing/ContactForm.tsx
import { useForm } from 'react-hook-form';

interface ContactFormData {
  name: string;
  email: string;
  company: string;
  phone?: string;
  message: string;
}

export const ContactForm: React.FC = () => {
  const { t } = useTranslation();
  const { register, handleSubmit, formState: { errors }, reset } = useForm<ContactFormData>();
  const [submitted, setSubmitted] = useState(false);

  const onSubmit = async (data: ContactFormData) => {
    try {
      await fetch('/api/v1/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      
      setSubmitted(true);
      reset();
      
      // Hide success message after 5 seconds
      setTimeout(() => setSubmitted(false), 5000);
    } catch (error) {
      console.error('Contact form submission failed:', error);
    }
  };

  return (
    <section className="contact-form">
      <h2>{t('landing.contact.section_title')}</h2>
      
      {submitted ? (
        <div className="success-message">
          {t('landing.contact.success_message')}
        </div>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-field">
            <label htmlFor="name">{t('landing.contact.name_label')}</label>
            <input
              id="name"
              type="text"
              {...register('name', { required: true })}
              aria-invalid={errors.name ? 'true' : 'false'}
            />
            {errors.name && (
              <span className="error">{t('landing.contact.name_required')}</span>
            )}
          </div>
          
          <div className="form-field">
            <label htmlFor="email">{t('landing.contact.email_label')}</label>
            <input
              id="email"
              type="email"
              {...register('email', { 
                required: true,
                pattern: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i
              })}
              aria-invalid={errors.email ? 'true' : 'false'}
            />
            {errors.email && (
              <span className="error">{t('landing.contact.email_invalid')}</span>
            )}
          </div>
          
          <div className="form-field">
            <label htmlFor="company">{t('landing.contact.company_label')}</label>
            <input
              id="company"
              type="text"
              {...register('company', { required: true })}
              aria-invalid={errors.company ? 'true' : 'false'}
            />
            {errors.company && (
              <span className="error">{t('landing.contact.company_required')}</span>
            )}
          </div>
          
          <div className="form-field">
            <label htmlFor="phone">{t('landing.contact.phone_label')}</label>
            <input
              id="phone"
              type="tel"
              {...register('phone')}
            />
          </div>
          
          <div className="form-field">
            <label htmlFor="message">{t('landing.contact.message_label')}</label>
            <textarea
              id="message"
              rows={5}
              {...register('message', { required: true })}
              aria-invalid={errors.message ? 'true' : 'false'}
            />
            {errors.message && (
              <span className="error">{t('landing.contact.message_required')}</span>
            )}
          </div>
          
          <Button type="submit" variant="primary">
            {t('landing.contact.submit_button')}
          </Button>
        </form>
      )}
    </section>
  );
};
```

**Crisp Chat Widget Integration:**
```typescript
// frontend/src/components/ChatWidget.tsx
import { useEffect } from 'react';

export const ChatWidget: React.FC = () => {
  useEffect(() => {
    // Load Crisp script
    window.$crisp = [];
    window.CRISP_WEBSITE_ID = import.meta.env.VITE_CRISP_WEBSITE_ID;
    
    const script = document.createElement('script');
    script.src = 'https://client.crisp.chat/l.js';
    script.async = true;
    document.head.appendChild(script);

    return () => {
      // Cleanup on unmount
      if (window.$crisp) {
        window.$crisp.push(['do', 'chat:hide']);
      }
    };
  }, []);

  return null; // Widget is injected by Crisp script
};
```



### 2. Internationalization (i18n) System

#### 2.1 react-i18next Configuration

**Setup:**
```typescript
// frontend/src/i18n/config.ts
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import enUS from './locales/en-US.json';
import arSA from './locales/ar-SA.json';

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      'en-US': { translation: enUS },
      'ar-SA': { translation: arSA }
    },
    fallbackLng: 'en-US',
    debug: import.meta.env.DEV,
    
    interpolation: {
      escapeValue: false // React already escapes
    },
    
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage']
    },
    
    react: {
      useSuspense: true
    }
  });

export default i18n;
```

**Usage in Components:**
```typescript
import { useTranslation } from 'react-i18next';

export const MyComponent: React.FC = () => {
  const { t, i18n } = useTranslation();

  return (
    <div>
      <h1>{t('welcome_message')}</h1>
      <p>{t('user_greeting', { name: 'Ahmed' })}</p>
      <p>Current language: {i18n.language}</p>
    </div>
  );
};
```

#### 2.2 Translation File Structure

**English (en-US.json):**
```json
{
  "common": {
    "save": "Save",
    "cancel": "Cancel",
    "delete": "Delete",
    "edit": "Edit",
    "search": "Search",
    "loading": "Loading...",
    "error": "An error occurred"
  },
  "auth": {
    "login": "Login",
    "logout": "Logout",
    "email": "Email",
    "password": "Password",
    "forgot_password": "Forgot password?",
    "sign_up": "Sign up"
  },
  "dashboard": {
    "title": "Dashboard",
    "total_leads": "Total Leads",
    "conversion_rate": "Conversion Rate",
    "revenue_pipeline": "Revenue Pipeline",
    "average_deal_size": "Average Deal Size",
    "date_range": {
      "last_7_days": "Last 7 Days",
      "last_30_days": "Last 30 Days",
      "last_quarter": "Last Quarter",
      "last_year": "Last Year",
      "custom": "Custom Range"
    }
  },
  "leads": {
    "title": "Leads",
    "create_lead": "Create Lead",
    "search_placeholder": "Search by name, email, phone, or company",
    "filters": {
      "all": "All",
      "hot": "Hot",
      "warm": "Warm",
      "cold": "Cold",
      "unassigned": "Unassigned",
      "my_leads": "My Leads"
    },
    "columns": {
      "name": "Name",
      "email": "Email",
      "company": "Company",
      "score": "Score",
      "status": "Status",
      "owner": "Owner",
      "created": "Created"
    }
  }
}
```

**Arabic (ar-SA.json):**
```json
{
  "common": {
    "save": "حفظ",
    "cancel": "إلغاء",
    "delete": "حذف",
    "edit": "تعديل",
    "search": "بحث",
    "loading": "جاري التحميل...",
    "error": "حدث خطأ"
  },
  "auth": {
    "login": "تسجيل الدخول",
    "logout": "تسجيل الخروج",
    "email": "البريد الإلكتروني",
    "password": "كلمة المرور",
    "forgot_password": "نسيت كلمة المرور؟",
    "sign_up": "إنشاء حساب"
  },
  "dashboard": {
    "title": "لوحة التحكم",
    "total_leads": "إجمالي العملاء المحتملين",
    "conversion_rate": "معدل التحويل",
    "revenue_pipeline": "خط أنابيب الإيرادات",
    "average_deal_size": "متوسط حجم الصفقة",
    "date_range": {
      "last_7_days": "آخر ٧ أيام",
      "last_30_days": "آخر ٣٠ يومًا",
      "last_quarter": "الربع الأخير",
      "last_year": "السنة الماضية",
      "custom": "نطاق مخصص"
    }
  },
  "leads": {
    "title": "العملاء المحتملون",
    "create_lead": "إنشاء عميل محتمل",
    "search_placeholder": "البحث بالاسم أو البريد الإلكتروني أو الهاتف أو الشركة",
    "filters": {
      "all": "الكل",
      "hot": "ساخن",
      "warm": "دافئ",
      "cold": "بارد",
      "unassigned": "غير مخصص",
      "my_leads": "عملائي المحتملون"
    },
    "columns": {
      "name": "الاسم",
      "email": "البريد الإلكتروني",
      "company": "الشركة",
      "score": "النقاط",
      "status": "الحالة",
      "owner": "المالك",
      "created": "تاريخ الإنشاء"
    }
  }
}
```

#### 2.3 Language Switcher Component

**Component Structure:**
```typescript
// frontend/src/components/LanguageSwitcher.tsx
import { useTranslation } from 'react-i18next';
import { useState, useRef, useEffect } from 'react';

const LANGUAGES = [
  { code: 'en-US', name: 'English', flag: '🇺🇸' },
  { code: 'ar-SA', name: 'العربية', flag: '🇸🇦' }
];

export const LanguageSwitcher: React.FC = () => {
  const { i18n } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const currentLanguage = LANGUAGES.find(lang => lang.code === i18n.language) || LANGUAGES[0];

  const changeLanguage = async (languageCode: string) => {
    await i18n.changeLanguage(languageCode);
    
    // Update document direction
    const direction = languageCode === 'ar-SA' ? 'rtl' : 'ltr';
    document.documentElement.setAttribute('dir', direction);
    document.documentElement.setAttribute('lang', languageCode);
    
    // Save to user profile if authenticated
    if (window.user) {
      await fetch('/api/v1/user/preferences', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ language: languageCode })
      });
    }
    
    setIsOpen(false);
  };

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="language-switcher" ref={dropdownRef}>
      <button
        className="language-button"
        onClick={() => setIsOpen(!isOpen)}
        aria-label="Change language"
        aria-expanded={isOpen}
      >
        <span className="flag">{currentLanguage.flag}</span>
        <span className="language-code">{currentLanguage.code.split('-')[0].toUpperCase()}</span>
      </button>
      
      {isOpen && (
        <div className="language-dropdown">
          {LANGUAGES.map((language) => (
            <button
              key={language.code}
              className={`language-option ${language.code === i18n.language ? 'active' : ''}`}
              onClick={() => changeLanguage(language.code)}
            >
              <span className="flag">{language.flag}</span>
              <span className="language-name">{language.name}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
};
```

#### 2.4 Number and Date Formatting

**Formatter Utilities:**
```typescript
// frontend/src/i18n/formatters/numberFormatter.ts
export const useNumberFormatter = () => {
  const { i18n } = useTranslation();
  const locale = i18n.language;

  const formatNumber = (value: number, options?: Intl.NumberFormatOptions) => {
    return new Intl.NumberFormat(locale, options).format(value);
  };

  const formatCurrency = (value: number, currency: string = 'USD') => {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency
    }).format(value);
  };

  const formatPercent = (value: number) => {
    return new Intl.NumberFormat(locale, {
      style: 'percent',
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }).format(value / 100);
  };

  return { formatNumber, formatCurrency, formatPercent };
};
```

```typescript
// frontend/src/i18n/formatters/dateFormatter.ts
export const useDateFormatter = () => {
  const { i18n } = useTranslation();
  const locale = i18n.language;

  const formatDate = (date: Date | string, options?: Intl.DateTimeFormatOptions) => {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    return new Intl.DateTimeFormat(locale, options).format(dateObj);
  };

  const formatShortDate = (date: Date | string) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const formatLongDate = (date: Date | string) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  const formatDateTime = (date: Date | string) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatRelativeTime = (date: Date | string) => {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - dateObj.getTime()) / 1000);

    if (diffInSeconds < 60) return i18n.t('time.just_now');
    if (diffInSeconds < 3600) return i18n.t('time.minutes_ago', { count: Math.floor(diffInSeconds / 60) });
    if (diffInSeconds < 86400) return i18n.t('time.hours_ago', { count: Math.floor(diffInSeconds / 3600) });
    if (diffInSeconds < 604800) return i18n.t('time.days_ago', { count: Math.floor(diffInSeconds / 86400) });
    
    return formatShortDate(dateObj);
  };

  return { formatDate, formatShortDate, formatLongDate, formatDateTime, formatRelativeTime };
};
```

**Arabic Number Format Setting:**
```typescript
// frontend/src/components/Settings/NumberFormatSetting.tsx
export const NumberFormatSetting: React.FC = () => {
  const { t, i18n } = useTranslation();
  const [format, setFormat] = useState<'western' | 'arabic-indic'>('western');

  const handleFormatChange = async (newFormat: 'western' | 'arabic-indic') => {
    setFormat(newFormat);
    
    // Save to user preferences
    await fetch('/api/v1/user/preferences', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number_format: newFormat })
    });
  };

  // Only show for Arabic locale
  if (i18n.language !== 'ar-SA') return null;

  return (
    <div className="setting-group">
      <label>{t('settings.number_format')}</label>
      <div className="radio-group">
        <label>
          <input
            type="radio"
            value="western"
            checked={format === 'western'}
            onChange={() => handleFormatChange('western')}
          />
          <span>{t('settings.number_format_western')} (0-9)</span>
        </label>
        <label>
          <input
            type="radio"
            value="arabic-indic"
            checked={format === 'arabic-indic'}
            onChange={() => handleFormatChange('arabic-indic')}
          />
          <span>{t('settings.number_format_arabic')} (٠-٩)</span>
        </label>
      </div>
    </div>
  );
};
```

#### 2.5 Translation Coverage Tracking

**Coverage Report Script:**
```typescript
// scripts/translation-coverage.ts
import enUS from '../frontend/src/i18n/locales/en-US.json';
import arSA from '../frontend/src/i18n/locales/ar-SA.json';

function flattenObject(obj: any, prefix = ''): Record<string, string> {
  return Object.keys(obj).reduce((acc, key) => {
    const value = obj[key];
    const newKey = prefix ? `${prefix}.${key}` : key;
    
    if (typeof value === 'object' && value !== null) {
      Object.assign(acc, flattenObject(value, newKey));
    } else {
      acc[newKey] = value;
    }
    
    return acc;
  }, {} as Record<string, string>);
}

function calculateCoverage() {
  const enFlat = flattenObject(enUS);
  const arFlat = flattenObject(arSA);
  
  const totalKeys = Object.keys(enFlat).length;
  const translatedKeys = Object.keys(arFlat).length;
  const missingKeys = Object.keys(enFlat).filter(key => !arFlat[key]);
  
  const coverage = (translatedKeys / totalKeys) * 100;
  
  console.log('Translation Coverage Report');
  console.log('===========================');
  console.log(`Total keys: ${totalKeys}`);
  console.log(`Translated keys: ${translatedKeys}`);
  console.log(`Coverage: ${coverage.toFixed(2)}%`);
  
  if (missingKeys.length > 0) {
    console.log('\nMissing translations:');
    missingKeys.forEach(key => console.log(`  - ${key}`));
  }
  
  return coverage >= 100;
}

const isComplete = calculateCoverage();
process.exit(isComplete ? 0 : 1);
```

**Run in CI/CD:**
```yaml
# .github/workflows/translation-check.yml
name: Translation Coverage Check

on: [push, pull_request]

jobs:
  check-translations:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npm run translation:check
```



### 3. Dashboard UX Refresh

#### 3.1 Dashboard Layout

**Component Structure:**
```typescript
// frontend/src/pages/Dashboard/Dashboard.tsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

interface DateRange {
  start: Date;
  end: Date;
  preset: 'last_7_days' | 'last_30_days' | 'last_quarter' | 'last_year' | 'custom';
}

export const Dashboard: React.FC = () => {
  const { t } = useTranslation();
  const [dateRange, setDateRange] = useState<DateRange>({
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000),
    end: new Date(),
    preset: 'last_30_days'
  });

  const { data: kpiData, isLoading } = useQuery({
    queryKey: ['dashboard-kpis', dateRange],
    queryFn: () => fetchDashboardKPIs(dateRange),
    staleTime: 60000 // 1 minute
  });

  return (
    <div className="dashboard">
      <div className="dashboard-header">
        <h1>{t('dashboard.title')}</h1>
        <DateRangeFilter value={dateRange} onChange={setDateRange} />
      </div>
      
      {isLoading ? (
        <DashboardSkeleton />
      ) : (
        <>
          <KPIGrid data={kpiData} />
          <ChartsGrid data={kpiData} dateRange={dateRange} />
        </>
      )}
    </div>
  );
};
```

**Responsive Grid:**
```css
.dashboard {
  padding: 2rem;
  
  /* Mobile: 1 column */
  @media (max-width: 767px) {
    padding: 1rem;
  }
}

.kpi-grid {
  display: grid;
  gap: 1.5rem;
  margin-bottom: 2rem;
  
  /* Mobile: 1 column */
  grid-template-columns: 1fr;
  
  /* Tablet: 2 columns */
  @media (min-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
  
  /* Desktop: 4 columns */
  @media (min-width: 1024px) {
    grid-template-columns: repeat(4, 1fr);
  }
}

.charts-grid {
  display: grid;
  gap: 1.5rem;
  
  /* Mobile: 1 column */
  grid-template-columns: 1fr;
  
  /* Desktop: 2 columns */
  @media (min-width: 1024px) {
    grid-template-columns: repeat(2, 1fr);
  }
}
```

#### 3.2 KPI Cards

**Component Structure:**
```typescript
// frontend/src/components/Dashboard/KPICard.tsx
interface KPICardProps {
  title: string;
  value: number | string;
  trend: 'up' | 'down' | 'neutral';
  percentageChange: number;
  format?: 'number' | 'currency' | 'percent';
  currency?: string;
}

export const KPICard: React.FC<KPICardProps> = ({
  title,
  value,
  trend,
  percentageChange,
  format = 'number',
  currency = 'USD'
}) => {
  const { formatNumber, formatCurrency, formatPercent } = useNumberFormatter();

  const formattedValue = () => {
    if (format === 'currency') return formatCurrency(value as number, currency);
    if (format === 'percent') return formatPercent(value as number);
    return formatNumber(value as number);
  };

  const trendIcon = () => {
    if (trend === 'up') return <TrendUpIcon className="trend-up" />;
    if (trend === 'down') return <TrendDownIcon className="trend-down" />;
    return <TrendNeutralIcon className="trend-neutral" />;
  };

  return (
    <div className="kpi-card">
      <div className="kpi-header">
        <h3 className="kpi-title">{title}</h3>
      </div>
      
      <div className="kpi-value">{formattedValue()}</div>
      
      <div className="kpi-trend">
        {trendIcon()}
        <span className={`trend-percentage trend-${trend}`}>
          {Math.abs(percentageChange)}%
        </span>
        <span className="trend-label">vs previous period</span>
      </div>
    </div>
  );
};
```

**KPI Grid Component:**
```typescript
// frontend/src/components/Dashboard/KPIGrid.tsx
interface DashboardKPIs {
  totalLeads: number;
  totalLeadsTrend: 'up' | 'down' | 'neutral';
  totalLeadsChange: number;
  
  conversionRate: number;
  conversionRateTrend: 'up' | 'down' | 'neutral';
  conversionRateChange: number;
  
  revenuePipeline: number;
  revenuePipelineTrend: 'up' | 'down' | 'neutral';
  revenuePipelineChange: number;
  
  averageDealSize: number;
  averageDealSizeTrend: 'up' | 'down' | 'neutral';
  averageDealSizeChange: number;
}

export const KPIGrid: React.FC<{ data: DashboardKPIs }> = ({ data }) => {
  const { t } = useTranslation();

  return (
    <div className="kpi-grid">
      <KPICard
        title={t('dashboard.total_leads')}
        value={data.totalLeads}
        trend={data.totalLeadsTrend}
        percentageChange={data.totalLeadsChange}
        format="number"
      />
      
      <KPICard
        title={t('dashboard.conversion_rate')}
        value={data.conversionRate}
        trend={data.conversionRateTrend}
        percentageChange={data.conversionRateChange}
        format="percent"
      />
      
      <KPICard
        title={t('dashboard.revenue_pipeline')}
        value={data.revenuePipeline}
        trend={data.revenuePipelineTrend}
        percentageChange={data.revenuePipelineChange}
        format="currency"
        currency="USD"
      />
      
      <KPICard
        title={t('dashboard.average_deal_size')}
        value={data.averageDealSize}
        trend={data.averageDealSizeTrend}
        percentageChange={data.averageDealSizeChange}
        format="currency"
        currency="USD"
      />
    </div>
  );
};
```

#### 3.3 Interactive Charts with Recharts

**Bar Chart Component:**
```typescript
// frontend/src/components/Dashboard/DealsByStageChart.tsx
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface DealsByStageData {
  stage: string;
  count: number;
  value: number;
}

export const DealsByStageChart: React.FC<{ data: DealsByStageData[] }> = ({ data }) => {
  const { t, i18n } = useTranslation();
  const isRTL = i18n.language === 'ar-SA';

  return (
    <div className="chart-card">
      <h3>{t('dashboard.charts.deals_by_stage')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <BarChart 
          data={data}
          layout={isRTL ? 'horizontal' : 'vertical'}
          margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
        >
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis 
            dataKey="stage" 
            reversed={isRTL}
          />
          <YAxis orientation={isRTL ? 'right' : 'left'} />
          <Tooltip 
            content={<CustomTooltip />}
            cursor={{ fill: 'rgba(59, 130, 246, 0.1)' }}
          />
          <Bar 
            dataKey="count" 
            fill="#3b82f6"
            radius={[8, 8, 0, 0]}
            onClick={(data) => handleBarClick(data)}
          />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
};

const CustomTooltip: React.FC<any> = ({ active, payload }) => {
  const { formatNumber } = useNumberFormatter();
  
  if (active && payload && payload.length) {
    return (
      <div className="chart-tooltip">
        <p className="tooltip-label">{payload[0].payload.stage}</p>
        <p className="tooltip-value">
          {formatNumber(payload[0].value)} deals
        </p>
      </div>
    );
  }
  return null;
};
```

**Line Chart Component:**
```typescript
// frontend/src/components/Dashboard/LeadCaptureChart.tsx
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface LeadCaptureData {
  date: string;
  count: number;
}

export const LeadCaptureChart: React.FC<{ data: LeadCaptureData[] }> = ({ data }) => {
  const { t, i18n } = useTranslation();
  const { formatShortDate } = useDateFormatter();
  const isRTL = i18n.language === 'ar-SA';

  return (
    <div className="chart-card">
      <h3>{t('dashboard.charts.lead_capture_trend')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <LineChart 
          data={data}
          margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
        >
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis 
            dataKey="date"
            tickFormatter={(value) => formatShortDate(value)}
            reversed={isRTL}
          />
          <YAxis orientation={isRTL ? 'right' : 'left'} />
          <Tooltip 
            labelFormatter={(value) => formatShortDate(value)}
            content={<CustomTooltip />}
          />
          <Line 
            type="monotone"
            dataKey="count"
            stroke="#3b82f6"
            strokeWidth={2}
            dot={{ r: 4 }}
            activeDot={{ r: 6 }}
            animationDuration={500}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
};
```

**Pie Chart Component:**
```typescript
// frontend/src/components/Dashboard/AIScoreDistributionChart.tsx
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer, Legend } from 'recharts';

interface ScoreDistributionData {
  category: string;
  count: number;
  color: string;
}

const SCORE_CATEGORIES = [
  { name: 'Hot (80-100)', color: '#ef4444' },
  { name: 'Warm (50-79)', color: '#f59e0b' },
  { name: 'Cold (0-49)', color: '#3b82f6' }
];

export const AIScoreDistributionChart: React.FC<{ data: ScoreDistributionData[] }> = ({ data }) => {
  const { t } = useTranslation();

  return (
    <div className="chart-card">
      <h3>{t('dashboard.charts.ai_score_distribution')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <PieChart>
          <Pie
            data={data}
            dataKey="count"
            nameKey="category"
            cx="50%"
            cy="50%"
            outerRadius={80}
            label={(entry) => `${entry.category}: ${entry.count}`}
            animationDuration={500}
          >
            {data.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={entry.color} />
            ))}
          </Pie>
          <Tooltip content={<CustomTooltip />} />
          <Legend />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
};
```

**Area Chart Component:**
```typescript
// frontend/src/components/Dashboard/RevenuePipelineChart.tsx
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface RevenuePipelineData {
  date: string;
  value: number;
}

export const RevenuePipelineChart: React.FC<{ data: RevenuePipelineData[] }> = ({ data }) => {
  const { t, i18n } = useTranslation();
  const { formatShortDate } = useDateFormatter();
  const { formatCurrency } = useNumberFormatter();
  const isRTL = i18n.language === 'ar-SA';

  return (
    <div className="chart-card">
      <h3>{t('dashboard.charts.revenue_pipeline_trend')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <AreaChart 
          data={data}
          margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
        >
          <defs>
            <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8}/>
              <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis 
            dataKey="date"
            tickFormatter={(value) => formatShortDate(value)}
            reversed={isRTL}
          />
          <YAxis 
            orientation={isRTL ? 'right' : 'left'}
            tickFormatter={(value) => formatCurrency(value, 'USD')}
          />
          <Tooltip 
            labelFormatter={(value) => formatShortDate(value)}
            formatter={(value: number) => formatCurrency(value, 'USD')}
          />
          <Area 
            type="monotone"
            dataKey="value"
            stroke="#3b82f6"
            fillOpacity={1}
            fill="url(#colorValue)"
            animationDuration={500}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
};
```

#### 3.4 Date Range Filter

**Component Structure:**
```typescript
// frontend/src/components/Dashboard/DateRangeFilter.tsx
import { useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';

interface DateRangeFilterProps {
  value: DateRange;
  onChange: (range: DateRange) => void;
}

const PRESETS = [
  { key: 'last_7_days', days: 7 },
  { key: 'last_30_days', days: 30 },
  { key: 'last_quarter', days: 90 },
  { key: 'last_year', days: 365 }
];

export const DateRangeFilter: React.FC<DateRangeFilterProps> = ({ value, onChange }) => {
  const { t } = useTranslation();
  const [showCustomPicker, setShowCustomPicker] = useState(false);

  const handlePresetClick = (preset: typeof PRESETS[0]) => {
    const end = new Date();
    const start = new Date(Date.now() - preset.days * 24 * 60 * 60 * 1000);
    
    onChange({
      start,
      end,
      preset: preset.key as DateRange['preset']
    });
  };

  const handleCustomRange = (start: Date, end: Date) => {
    onChange({ start, end, preset: 'custom' });
    setShowCustomPicker(false);
  };

  return (
    <div className="date-range-filter">
      <div className="preset-buttons">
        {PRESETS.map((preset) => (
          <button
            key={preset.key}
            className={value.preset === preset.key ? 'active' : ''}
            onClick={() => handlePresetClick(preset)}
          >
            {t(`dashboard.date_range.${preset.key}`)}
          </button>
        ))}
        
        <button
          className={value.preset === 'custom' ? 'active' : ''}
          onClick={() => setShowCustomPicker(!showCustomPicker)}
        >
          {t('dashboard.date_range.custom')}
        </button>
      </div>
      
      {showCustomPicker && (
        <div className="custom-date-picker">
          <DatePicker
            selectsRange
            startDate={value.start}
            endDate={value.end}
            onChange={(dates) => {
              const [start, end] = dates;
              if (start && end) {
                handleCustomRange(start, end);
              }
            }}
            inline
          />
        </div>
      )}
    </div>
  );
};
```

#### 3.5 Leads List with Virtual Scrolling

**Component Structure:**
```typescript
// frontend/src/pages/Leads/LeadsList.tsx
import { FixedSizeList as List } from 'react-window';
import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useDebounce } from 'use-debounce';

interface Lead {
  id: string;
  name: string;
  email: string;
  company: string;
  score: number;
  status: string;
  owner: string;
  created_at: string;
}

type FilterType = 'all' | 'hot' | 'warm' | 'cold' | 'unassigned' | 'my_leads';

export const LeadsList: React.FC = () => {
  const { t } = useTranslation();
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch] = useDebounce(searchQuery, 300);
  const [activeFilter, setActiveFilter] = useState<FilterType>('all');
  const [sortColumn, setSortColumn] = useState<keyof Lead>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  const { data: leads, isLoading } = useQuery({
    queryKey: ['leads', debouncedSearch, activeFilter, sortColumn, sortDirection],
    queryFn: () => fetchLeads({
      search: debouncedSearch,
      filter: activeFilter,
      sortBy: sortColumn,
      sortDir: sortDirection
    })
  });

  const handleSort = (column: keyof Lead) => {
    if (sortColumn === column) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      setSortColumn(column);
      setSortDirection('asc');
    }
  };

  return (
    <div className="leads-list">
      <div className="leads-header">
        <h1>{t('leads.title')}</h1>
        <Button href="/leads/create">{t('leads.create_lead')}</Button>
      </div>
      
      {/* Search Bar */}
      <div className="search-bar">
        <SearchIcon />
        <input
          type="text"
          placeholder={t('leads.search_placeholder')}
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
        />
      </div>
      
      {/* Filter Chips */}
      <div className="filter-chips">
        {(['all', 'hot', 'warm', 'cold', 'unassigned', 'my_leads'] as FilterType[]).map((filter) => (
          <button
            key={filter}
            className={`filter-chip ${activeFilter === filter ? 'active' : ''}`}
            onClick={() => setActiveFilter(filter)}
          >
            {t(`leads.filters.${filter}`)}
          </button>
        ))}
      </div>
      
      {/* Table Header */}
      <div className="table-header">
        {(['name', 'email', 'company', 'score', 'status', 'owner', 'created_at'] as (keyof Lead)[]).map((column) => (
          <button
            key={column}
            className="column-header"
            onClick={() => handleSort(column)}
          >
            {t(`leads.columns.${column}`)}
            {sortColumn === column && (
              <SortIcon direction={sortDirection} />
            )}
          </button>
        ))}
      </div>
      
      {/* Virtual List */}
      {isLoading ? (
        <LeadsListSkeleton />
      ) : (
        <List
          height={600}
          itemCount={leads.length}
          itemSize={60}
          width="100%"
          overscanCount={10}
        >
          {({ index, style }) => (
            <LeadRow lead={leads[index]} style={style} />
          )}
        </List>
      )}
      
      {/* Scroll Position Indicator */}
      <div className="scroll-indicator">
        {t('leads.showing_count', { 
          start: 1, 
          end: Math.min(50, leads.length), 
          total: leads.length 
        })}
      </div>
    </div>
  );
};
```

**Lead Row Component:**
```typescript
// frontend/src/components/Leads/LeadRow.tsx
interface LeadRowProps {
  lead: Lead;
  style: React.CSSProperties;
}

export const LeadRow: React.FC<LeadRowProps> = ({ lead, style }) => {
  const { formatShortDate } = useDateFormatter();
  const navigate = useNavigate();

  const getScoreColor = (score: number) => {
    if (score >= 80) return 'text-red-600';
    if (score >= 50) return 'text-orange-600';
    return 'text-blue-600';
  };

  return (
    <div 
      className="lead-row" 
      style={style}
      onClick={() => navigate(`/leads/${lead.id}`)}
    >
      <div className="lead-cell">{lead.name}</div>
      <div className="lead-cell">{lead.email}</div>
      <div className="lead-cell">{lead.company}</div>
      <div className={`lead-cell ${getScoreColor(lead.score)}`}>
        {lead.score}
      </div>
      <div className="lead-cell">
        <StatusBadge status={lead.status} />
      </div>
      <div className="lead-cell">{lead.owner}</div>
      <div className="lead-cell">{formatShortDate(lead.created_at)}</div>
    </div>
  );
};
```



#### 3.6 Loading Skeletons

**Dashboard Skeleton:**
```typescript
// frontend/src/components/Skeletons/DashboardSkeleton.tsx
export const DashboardSkeleton: React.FC = () => {
  return (
    <div className="dashboard-skeleton">
      <div className="kpi-grid">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="skeleton-card">
            <div className="skeleton-line skeleton-title" />
            <div className="skeleton-line skeleton-value" />
            <div className="skeleton-line skeleton-trend" />
          </div>
        ))}
      </div>
      
      <div className="charts-grid">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="skeleton-chart">
            <div className="skeleton-line skeleton-chart-title" />
            <div className="skeleton-chart-body" />
          </div>
        ))}
      </div>
    </div>
  );
};
```

**Leads List Skeleton:**
```typescript
// frontend/src/components/Skeletons/LeadsListSkeleton.tsx
export const LeadsListSkeleton: React.FC = () => {
  return (
    <div className="leads-list-skeleton">
      {[...Array(10)].map((_, i) => (
        <div key={i} className="skeleton-row">
          <div className="skeleton-line skeleton-cell" />
          <div className="skeleton-line skeleton-cell" />
          <div className="skeleton-line skeleton-cell" />
          <div className="skeleton-line skeleton-cell" />
          <div className="skeleton-line skeleton-cell" />
          <div className="skeleton-line skeleton-cell" />
          <div className="skeleton-line skeleton-cell" />
        </div>
      ))}
    </div>
  );
};
```

**Skeleton Styles:**
```css
.skeleton-line {
  background: linear-gradient(
    90deg,
    #f0f0f0 0%,
    #e0e0e0 50%,
    #f0f0f0 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

/* RTL support */
[dir="rtl"] .skeleton-line {
  background: linear-gradient(
    -90deg,
    #f0f0f0 0%,
    #e0e0e0 50%,
    #f0f0f0 100%
  );
  background-size: 200% 100%;
}

@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

.skeleton-title {
  width: 60%;
  height: 16px;
  margin-bottom: 12px;
}

.skeleton-value {
  width: 40%;
  height: 32px;
  margin-bottom: 8px;
}

.skeleton-trend {
  width: 30%;
  height: 14px;
}

.skeleton-chart-body {
  width: 100%;
  height: 250px;
  background: linear-gradient(
    90deg,
    #f0f0f0 0%,
    #e0e0e0 50%,
    #f0f0f0 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
}
```

#### 3.7 Dark Mode Implementation

**Theme Provider:**
```typescript
// frontend/src/contexts/ThemeContext.tsx
import { createContext, useContext, useEffect, useState } from 'react';

type Theme = 'light' | 'dark';

interface ThemeContextType {
  theme: Theme;
  toggleTheme: () => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [theme, setTheme] = useState<Theme>(() => {
    // Check user preference
    const saved = localStorage.getItem('theme') as Theme;
    if (saved) return saved;
    
    // Check OS preference
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    
    return 'light';
  });

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    // Save to user profile if authenticated
    if (window.user) {
      fetch('/api/v1/user/preferences', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme })
      });
    }
  }, [theme]);

  const toggleTheme = () => {
    setTheme(prev => prev === 'light' ? 'dark' : 'light');
  };

  return (
    <ThemeContext.Provider value={{ theme, toggleTheme }}>
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) throw new Error('useTheme must be used within ThemeProvider');
  return context;
};
```

**Theme Toggle Component:**
```typescript
// frontend/src/components/ThemeToggle.tsx
export const ThemeToggle: React.FC = () => {
  const { theme, toggleTheme } = useTheme();

  return (
    <button
      className="theme-toggle"
      onClick={toggleTheme}
      aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}
    >
      {theme === 'light' ? <MoonIcon /> : <SunIcon />}
    </button>
  );
};
```

**CSS Variables:**
```css
:root[data-theme="light"] {
  --color-background: #ffffff;
  --color-surface: #f9fafb;
  --color-primary: #3b82f6;
  --color-text: #111827;
  --color-text-secondary: #6b7280;
  --color-border: #e5e7eb;
  --color-hover: #f3f4f6;
}

:root[data-theme="dark"] {
  --color-background: #1a1a1a;
  --color-surface: #2d2d2d;
  --color-primary: #3b82f6;
  --color-text: #e5e5e5;
  --color-text-secondary: #a3a3a3;
  --color-border: #404040;
  --color-hover: #3a3a3a;
}

body {
  background-color: var(--color-background);
  color: var(--color-text);
  transition: background-color 300ms ease, color 300ms ease;
}

.card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  transition: background-color 300ms ease, border-color 300ms ease;
}

.button {
  background-color: var(--color-primary);
  color: white;
  transition: background-color 300ms ease;
}

.button:hover {
  background-color: var(--color-hover);
}
```

#### 3.8 Mobile Responsive Design

**Responsive Breakpoints:**
```typescript
// frontend/src/styles/breakpoints.ts
export const breakpoints = {
  mobile: '(max-width: 767px)',
  tablet: '(min-width: 768px) and (max-width: 1023px)',
  desktop: '(min-width: 1024px)'
};

export const useMediaQuery = (query: string) => {
  const [matches, setMatches] = useState(window.matchMedia(query).matches);

  useEffect(() => {
    const mediaQuery = window.matchMedia(query);
    const handler = (e: MediaQueryListEvent) => setMatches(e.matches);
    
    mediaQuery.addEventListener('change', handler);
    return () => mediaQuery.removeEventListener('change', handler);
  }, [query]);

  return matches;
};
```

**Mobile Navigation:**
```typescript
// frontend/src/components/Navigation/MobileNav.tsx
export const MobileNav: React.FC = () => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const isMobile = useMediaQuery(breakpoints.mobile);

  if (!isMobile) return null;

  return (
    <>
      <button 
        className="hamburger-button"
        onClick={() => setIsOpen(!isOpen)}
        aria-label="Toggle menu"
      >
        {isOpen ? <CloseIcon /> : <MenuIcon />}
      </button>
      
      {isOpen && (
        <div className="mobile-menu">
          <nav>
            <a href="/dashboard">{t('nav.dashboard')}</a>
            <a href="/leads">{t('nav.leads')}</a>
            <a href="/contacts">{t('nav.contacts')}</a>
            <a href="/deals">{t('nav.deals')}</a>
            <a href="/inbox">{t('nav.inbox')}</a>
            <a href="/activities">{t('nav.activities')}</a>
            <a href="/settings">{t('nav.settings')}</a>
          </nav>
        </div>
      )}
    </>
  );
};
```

**Touch-Friendly Controls:**
```css
/* Minimum touch target size: 44x44px */
.button,
.link,
.icon-button {
  min-width: 44px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Increased spacing on mobile */
@media (max-width: 767px) {
  .form-field {
    margin-bottom: 1.5rem;
  }
  
  .button-group {
    gap: 1rem;
  }
  
  .card {
    padding: 1.5rem;
  }
}
```

#### 3.9 Animations and Micro-Interactions

**Page Transitions:**
```typescript
// frontend/src/components/PageTransition.tsx
import { motion, AnimatePresence } from 'framer-motion';
import { useLocation } from 'react-router-dom';

export const PageTransition: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const location = useLocation();
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReducedMotion) {
    return <>{children}</>;
  }

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={location.pathname}
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.2 }}
      >
        {children}
      </motion.div>
    </AnimatePresence>
  );
};
```

**Modal Animations:**
```typescript
// frontend/src/components/Modal.tsx
import { motion } from 'framer-motion';

export const Modal: React.FC<{ children: React.ReactNode; onClose: () => void }> = ({ 
  children, 
  onClose 
}) => {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  return (
    <motion.div
      className="modal-overlay"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ duration: prefersReducedMotion ? 0 : 0.15 }}
      onClick={onClose}
    >
      <motion.div
        className="modal-content"
        initial={{ scale: 0.95, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        exit={{ scale: 0.95, opacity: 0 }}
        transition={{ duration: prefersReducedMotion ? 0 : 0.15 }}
        onClick={(e) => e.stopPropagation()}
      >
        {children}
      </motion.div>
    </motion.div>
  );
};
```

**Button Interactions:**
```css
.button {
  transition: transform 100ms ease, box-shadow 100ms ease;
}

.button:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.button:active {
  transform: scale(0.98);
}

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
  .button {
    transition: none;
  }
  
  .button:active {
    transform: none;
  }
}
```

**List Item Animations:**
```typescript
// frontend/src/components/AnimatedList.tsx
import { motion, AnimatePresence } from 'framer-motion';

export const AnimatedList: React.FC<{ items: any[]; renderItem: (item: any) => React.ReactNode }> = ({ 
  items, 
  renderItem 
}) => {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReducedMotion) {
    return <>{items.map(renderItem)}</>;
  }

  return (
    <AnimatePresence>
      {items.map((item, index) => (
        <motion.div
          key={item.id}
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, height: 0 }}
          transition={{ duration: 0.2, delay: index * 0.05 }}
        >
          {renderItem(item)}
        </motion.div>
      ))}
    </AnimatePresence>
  );
};
```

#### 3.10 Accessibility Features

**Keyboard Navigation:**
```typescript
// frontend/src/hooks/useKeyboardNavigation.ts
export const useKeyboardNavigation = (items: any[], onSelect: (item: any) => void) => {
  const [focusedIndex, setFocusedIndex] = useState(0);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          setFocusedIndex(prev => Math.min(prev + 1, items.length - 1));
          break;
        case 'ArrowUp':
          e.preventDefault();
          setFocusedIndex(prev => Math.max(prev - 1, 0));
          break;
        case 'Enter':
          e.preventDefault();
          onSelect(items[focusedIndex]);
          break;
        case 'Escape':
          e.preventDefault();
          setFocusedIndex(0);
          break;
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [items, focusedIndex, onSelect]);

  return focusedIndex;
};
```

**Focus Management:**
```typescript
// frontend/src/hooks/useFocusTrap.ts
export const useFocusTrap = (ref: React.RefObject<HTMLElement>) => {
  useEffect(() => {
    const element = ref.current;
    if (!element) return;

    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    const firstElement = focusableElements[0] as HTMLElement;
    const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement;

    const handleTabKey = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        }
      } else {
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        }
      }
    };

    element.addEventListener('keydown', handleTabKey);
    firstElement?.focus();

    return () => element.removeEventListener('keydown', handleTabKey);
  }, [ref]);
};
```

**ARIA Labels:**
```typescript
// Example: Accessible button with loading state
export const AccessibleButton: React.FC<{
  children: React.ReactNode;
  onClick: () => void;
  loading?: boolean;
  disabled?: boolean;
}> = ({ children, onClick, loading, disabled }) => {
  return (
    <button
      onClick={onClick}
      disabled={disabled || loading}
      aria-busy={loading}
      aria-disabled={disabled || loading}
    >
      {loading && <span className="sr-only">Loading...</span>}
      {children}
    </button>
  );
};
```



### 4. Performance Optimization

#### 4.1 Code Splitting and Lazy Loading

**Route-Based Code Splitting:**
```typescript
// frontend/src/App.tsx
import { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';

// Lazy load route components
const LandingPage = lazy(() => import('./pages/Landing/LandingPage'));
const Dashboard = lazy(() => import('./pages/Dashboard/Dashboard'));
const LeadsList = lazy(() => import('./pages/Leads/LeadsList'));
const LeadDetail = lazy(() => import('./pages/Leads/LeadDetail'));
const Inbox = lazy(() => import('./pages/Inbox/Inbox'));
const Settings = lazy(() => import('./pages/Settings/Settings'));

export const App: React.FC = () => {
  return (
    <BrowserRouter>
      <Suspense fallback={<PageLoadingSkeleton />}>
        <Routes>
          <Route path="/" element={<LandingPage />} />
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/leads" element={<LeadsList />} />
          <Route path="/leads/:id" element={<LeadDetail />} />
          <Route path="/inbox" element={<Inbox />} />
          <Route path="/settings" element={<Settings />} />
        </Routes>
      </Suspense>
    </BrowserRouter>
  );
};
```

**Component-Level Lazy Loading:**
```typescript
// Lazy load heavy components
const HeavyChart = lazy(() => import('./components/HeavyChart'));

export const Dashboard: React.FC = () => {
  return (
    <div>
      <Suspense fallback={<ChartSkeleton />}>
        <HeavyChart data={data} />
      </Suspense>
    </div>
  );
};
```

#### 4.2 Image Optimization

**Responsive Images:**
```typescript
// frontend/src/components/ResponsiveImage.tsx
interface ResponsiveImageProps {
  src: string;
  alt: string;
  sizes?: string;
}

export const ResponsiveImage: React.FC<ResponsiveImageProps> = ({ 
  src, 
  alt, 
  sizes = '100vw' 
}) => {
  const webpSrc = src.replace(/\.(jpg|png)$/, '.webp');
  
  return (
    <picture>
      <source type="image/webp" srcSet={webpSrc} sizes={sizes} />
      <img src={src} alt={alt} loading="lazy" />
    </picture>
  );
};
```

**Image Preloading:**
```html
<!-- In index.html for critical images -->
<link rel="preload" as="image" href="/images/hero-dashboard.webp" />
```

#### 4.3 Asset Compression and CDN

**Vite Build Configuration:**
```typescript
// vite.config.ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import compression from 'vite-plugin-compression';

export default defineConfig({
  plugins: [
    react(),
    compression({
      algorithm: 'brotliCompress',
      ext: '.br'
    })
  ],
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom', 'react-router-dom'],
          'charts': ['recharts'],
          'i18n': ['react-i18next', 'i18next'],
          'forms': ['react-hook-form']
        }
      }
    },
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true
      }
    }
  }
});
```

**CDN Configuration:**
```typescript
// Serve static assets from CDN
const CDN_URL = import.meta.env.VITE_CDN_URL || '';

export const getAssetUrl = (path: string) => {
  return `${CDN_URL}${path}`;
};
```

#### 4.4 Font Optimization

**Font Preloading:**
```html
<!-- In index.html -->
<link rel="preload" href="/fonts/inter-var.woff2" as="font" type="font/woff2" crossorigin />
<link rel="preload" href="/fonts/noto-sans-arabic.woff2" as="font" type="font/woff2" crossorigin />
```

**Font Display Strategy:**
```css
@font-face {
  font-family: 'Inter';
  src: url('/fonts/inter-var.woff2') format('woff2');
  font-weight: 100 900;
  font-display: swap; /* Prevent FOIT */
}

@font-face {
  font-family: 'Noto Sans Arabic';
  src: url('/fonts/noto-sans-arabic.woff2') format('woff2');
  font-weight: 100 900;
  font-display: swap;
  unicode-range: U+0600-06FF; /* Arabic characters only */
}
```

#### 4.5 Performance Monitoring

**Web Vitals Tracking:**
```typescript
// frontend/src/utils/webVitals.ts
import { getCLS, getFID, getFCP, getLCP, getTTFB } from 'web-vitals';

export const reportWebVitals = () => {
  getCLS(sendToAnalytics);
  getFID(sendToAnalytics);
  getFCP(sendToAnalytics);
  getLCP(sendToAnalytics);
  getTTFB(sendToAnalytics);
};

const sendToAnalytics = (metric: any) => {
  // Send to analytics service
  fetch('/api/v1/analytics/web-vitals', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: metric.name,
      value: metric.value,
      rating: metric.rating,
      delta: metric.delta,
      id: metric.id
    })
  });
};
```

## Data Models

### Translation File Schema

**Structure:**
```typescript
interface TranslationFile {
  [namespace: string]: {
    [key: string]: string | TranslationFile;
  };
}

// Example:
const enUS: TranslationFile = {
  common: {
    save: "Save",
    cancel: "Cancel"
  },
  dashboard: {
    title: "Dashboard",
    kpis: {
      total_leads: "Total Leads",
      conversion_rate: "Conversion Rate"
    }
  }
};
```

**Validation Schema:**
```typescript
// Ensure all keys in base locale exist in other locales
interface TranslationValidation {
  locale: string;
  totalKeys: number;
  translatedKeys: number;
  missingKeys: string[];
  coverage: number; // percentage
}
```

### Dashboard Configuration Schema

**User Dashboard Preferences:**
```typescript
interface DashboardConfig {
  userId: string;
  widgets: WidgetConfig[];
  dateRangePreset: 'last_7_days' | 'last_30_days' | 'last_quarter' | 'last_year' | 'custom';
  customDateRange?: {
    start: string; // ISO 8601
    end: string;   // ISO 8601
  };
}

interface WidgetConfig {
  id: string;
  type: 'kpi' | 'chart';
  position: {
    x: number;
    y: number;
    width: number;
    height: number;
  };
  config: KPIConfig | ChartConfig;
}

interface KPIConfig {
  metric: 'total_leads' | 'conversion_rate' | 'revenue_pipeline' | 'average_deal_size';
  format: 'number' | 'currency' | 'percent';
  currency?: string;
}

interface ChartConfig {
  chartType: 'bar' | 'line' | 'pie' | 'area';
  dataSource: string;
  filters?: Record<string, any>;
}
```

**Serialization:**
```typescript
// Serialize to JSON for storage
const serializeDashboardConfig = (config: DashboardConfig): string => {
  return JSON.stringify(config, null, 2);
};

// Deserialize from JSON
const deserializeDashboardConfig = (json: string): DashboardConfig => {
  return JSON.parse(json);
};
```

### Lead Data Model (Frontend)

**TypeScript Interface:**
```typescript
interface Lead {
  id: string;
  name: string;
  email: string;
  phone?: string;
  company?: string;
  score: number; // 0-100
  status: 'new' | 'contacted' | 'qualified' | 'unqualified';
  source: string;
  owner?: {
    id: string;
    name: string;
  };
  tags: string[];
  customFields: Record<string, any>;
  createdAt: string; // ISO 8601
  updatedAt: string; // ISO 8601
}
```

### Chart Data Models

**Bar Chart Data:**
```typescript
interface BarChartData {
  category: string;
  value: number;
  color?: string;
}
```

**Line Chart Data:**
```typescript
interface LineChartData {
  date: string; // ISO 8601
  value: number;
}
```

**Pie Chart Data:**
```typescript
interface PieChartData {
  category: string;
  value: number;
  color: string;
  percentage: number;
}
```

**Area Chart Data:**
```typescript
interface AreaChartData {
  date: string; // ISO 8601
  value: number;
  label?: string;
}
```

### User Preferences Model

**Schema:**
```typescript
interface UserPreferences {
  userId: string;
  language: 'en-US' | 'ar-SA';
  theme: 'light' | 'dark';
  numberFormat?: 'western' | 'arabic-indic'; // Only for ar-SA
  dateFormat?: 'gregorian' | 'hijri'; // Only for ar-SA
  timezone: string; // IANA timezone
  notifications: {
    email: boolean;
    push: boolean;
    inApp: boolean;
  };
}
```

**Backend Storage:**
```sql
-- Add to users table
ALTER TABLE users ADD COLUMN preferences JSON;

-- Example stored value:
{
  "language": "ar-SA",
  "theme": "dark",
  "numberFormat": "western",
  "dateFormat": "gregorian",
  "timezone": "Asia/Riyadh",
  "notifications": {
    "email": true,
    "push": true,
    "inApp": true
  }
}
```

### Demo Environment Data Model

**Demo Tenant Configuration:**
```typescript
interface DemoTenant {
  id: string;
  name: 'Demo Environment';
  isDemo: true;
  readOnly: true;
  resetSchedule: {
    frequency: 'daily';
    time: '00:00 UTC';
  };
  sampleData: {
    leads: number;
    deals: number;
    contacts: number;
    activities: number;
    messages: number;
  };
  lastReset: string; // ISO 8601
  nextReset: string; // ISO 8601
}
```

### Contact Form Submission Model

**Schema:**
```typescript
interface ContactFormSubmission {
  id: string;
  name: string;
  email: string;
  company: string;
  phone?: string;
  message: string;
  source: 'landing_page' | 'chat_widget';
  ipAddress: string;
  userAgent: string;
  submittedAt: string; // ISO 8601
  leadId?: string; // Created lead ID
  status: 'pending' | 'contacted' | 'converted';
}
```

**Backend Processing:**
```php
// modular_core/modules/Platform/Contact/ContactFormController.php
public function submit(Request $request): Response {
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'company' => 'required|string|max:255',
        'phone' => 'nullable|string|max:50',
        'message' => 'required|string|max:2000'
    ]);
    
    // Create lead in sales CRM tenant
    $lead = Lead::create([
        'tenant_id' => config('app.sales_tenant_id'),
        'name' => $data['name'],
        'email' => $data['email'],
        'company' => $data['company'],
        'phone' => $data['phone'] ?? null,
        'source' => 'website_contact_form',
        'notes' => $data['message'],
        'status' => 'new'
    ]);
    
    // Send notification to sales team
    Mail::to(config('app.sales_email'))
        ->send(new NewContactFormSubmission($lead));
    
    return response()->json([
        'success' => true,
        'message' => 'Thank you! We\'ll get back to you within 24 hours.'
    ]);
}
```

