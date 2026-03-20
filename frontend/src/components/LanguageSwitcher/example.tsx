/**
 * Language Switcher Integration Example
 * Shows how to integrate the LanguageSwitcher into your layout
 */

import React from 'react';
import { LanguageSwitcher } from './LanguageSwitcher';

// Example 1: In a header/navbar
export const HeaderWithLanguageSwitcher: React.FC = () => {
  return (
    <header style={{ 
      display: 'flex', 
      justifyContent: 'space-between', 
      alignItems: 'center',
      padding: '1rem 2rem',
      borderBottom: '1px solid #e5e7eb'
    }}>
      <div className="logo">
        <h1>NexSaaS</h1>
      </div>
      
      <nav style={{ display: 'flex', gap: '2rem', alignItems: 'center' }}>
        <a href="/dashboard">Dashboard</a>
        <a href="/leads">Leads</a>
        <a href="/contacts">Contacts</a>
        <a href="/deals">Deals</a>
        
        {/* Language Switcher */}
        <LanguageSwitcher />
      </nav>
    </header>
  );
};

// Example 2: In a settings page
export const SettingsWithLanguageSwitcher: React.FC = () => {
  return (
    <div className="settings-page">
      <h1>Settings</h1>
      
      <section className="settings-section">
        <h2>Language & Region</h2>
        
        <div className="setting-row">
          <label>Interface Language</label>
          <LanguageSwitcher />
        </div>
        
        <div className="setting-row">
          <label>Timezone</label>
          <select>
            <option>UTC</option>
            <option>Asia/Riyadh</option>
            <option>America/New_York</option>
          </select>
        </div>
      </section>
    </div>
  );
};

// Example 3: In a user menu dropdown
export const UserMenuWithLanguageSwitcher: React.FC = () => {
  return (
    <div className="user-menu">
      <button className="user-avatar">
        <img src="/avatar.jpg" alt="User" />
      </button>
      
      <div className="user-dropdown">
        <div className="user-info">
          <p>John Doe</p>
          <p>john@example.com</p>
        </div>
        
        <hr />
        
        <div className="menu-item">
          <span>Language</span>
          <LanguageSwitcher />
        </div>
        
        <hr />
        
        <button className="menu-item">Profile</button>
        <button className="menu-item">Settings</button>
        <button className="menu-item">Logout</button>
      </div>
    </div>
  );
};
