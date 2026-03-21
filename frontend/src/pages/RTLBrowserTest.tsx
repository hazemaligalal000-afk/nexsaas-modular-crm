/**
 * RTL Browser Test Page
 * Comprehensive visual test page for RTL layout verification across browsers
 * Requirements: 11
 * 
 * Test on:
 * - Safari iOS
 * - Chrome Android
 * - Chrome Desktop
 * - Firefox Desktop
 */

import React, { useState } from 'react';
import { useDirection, useIsRTL } from '../i18n/hooks/useDirection';
import '../styles/logical-properties.css';
import '../styles/rtl.css';
import './RTLBrowserTest.css';

export const RTLBrowserTest: React.FC = () => {
  const direction = useDirection();
  const isRTL = useIsRTL();
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [dropdownOpen, setDropdownOpen] = useState(false);

  return (
    <div className="rtl-browser-test">
      {/* Test Header */}
      <header className="test-header">
        <h1>RTL Browser Test Suite</h1>
        <div className="test-info-bar">
          <span className="badge">Direction: {direction.toUpperCase()}</span>
          <span className="badge">RTL: {isRTL ? 'Yes' : 'No'}</span>
          <span className="badge">Browser: {navigator.userAgent.split(' ').pop()}</span>
        </div>
      </header>

      <div className="test-content">
        {/* Section 1: Navigation Menus */}
        <section className="test-section">
          <h2>1. Navigation Menus</h2>
          <p className="test-description">
            Navigation items should flow from start to end. Active indicators should appear on the correct side.
          </p>
          
          <nav className="test-nav">
            <a href="#" className="nav-link active">Dashboard</a>
            <a href="#" className="nav-link">Leads</a>
            <a href="#" className="nav-link">Contacts</a>
            <a href="#" className="nav-link">Deals</a>
            <a href="#" className="nav-link">Inbox</a>
          </nav>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Items flow {isRTL ? 'right-to-left' : 'left-to-right'}, active indicator on {isRTL ? 'right' : 'left'}</span>
          </div>
        </section>

        {/* Section 2: Sidebar */}
        <section className="test-section">
          <h2>2. Sidebar Layout</h2>
          <p className="test-description">
            Sidebar should appear on the {isRTL ? 'right' : 'left'} side. Content should be pushed accordingly.
          </p>
          
          <div className="sidebar-test-container">
            <aside className={`test-sidebar ${sidebarOpen ? 'open' : 'closed'}`}>
              <div className="sidebar-header">
                <h3>Menu</h3>
                <button onClick={() => setSidebarOpen(!sidebarOpen)}>×</button>
              </div>
              <ul className="sidebar-menu">
                <li className="sidebar-item">
                  <span className="sidebar-icon">📊</span>
                  <span>Dashboard</span>
                </li>
                <li className="sidebar-item">
                  <span className="sidebar-icon">👥</span>
                  <span>Leads</span>
                </li>
                <li className="sidebar-item">
                  <span className="sidebar-icon">📧</span>
                  <span>Inbox</span>
                </li>
              </ul>
            </aside>
            <div className="sidebar-content">
              <p>Main content area. This should be pushed by the sidebar.</p>
            </div>
          </div>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Sidebar on {isRTL ? 'right' : 'left'}, content flows properly</span>
          </div>
        </section>

        {/* Section 3: Forms */}
        <section className="test-section">
          <h2>3. Form Layouts</h2>
          <p className="test-description">
            Labels should align to the start, inputs should fill properly, help text should align correctly.
          </p>
          
          <form className="test-form">
            <div className="form-group">
              <label htmlFor="name" className="form-label">Full Name</label>
              <input 
                type="text" 
                id="name" 
                className="form-input" 
                placeholder="Enter your name"
              />
              <span className="form-help-text">This is help text</span>
            </div>

            <div className="form-group">
              <label htmlFor="email" className="form-label">Email Address</label>
              <input 
                type="email" 
                id="email" 
                className="form-input" 
                placeholder="email@example.com"
              />
            </div>

            <div className="form-group">
              <label htmlFor="phone" className="form-label">Phone Number</label>
              <input 
                type="tel" 
                id="phone" 
                className="form-input" 
                placeholder="+1234567890"
                dir="ltr"
              />
              <span className="form-help-text">Phone numbers remain LTR</span>
            </div>

            <div className="form-group">
              <label htmlFor="message" className="form-label">Message</label>
              <textarea 
                id="message" 
                className="form-input" 
                rows={4}
                placeholder="Enter your message"
              />
            </div>

            <div className="form-actions">
              <button type="button" className="btn btn-secondary">Cancel</button>
              <button type="submit" className="btn btn-primary">Submit</button>
            </div>
          </form>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Labels align to start, buttons flow {isRTL ? 'right-to-left' : 'left-to-right'}</span>
          </div>
        </section>

        {/* Section 4: Button Groups */}
        <section className="test-section">
          <h2>4. Button Groups</h2>
          <p className="test-description">
            Button groups should reverse order in RTL. Primary action should be on the {isRTL ? 'left' : 'right'}.
          </p>
          
          <div className="button-group-test">
            <div className="button-group">
              <button className="btn btn-secondary">Cancel</button>
              <button className="btn btn-secondary">Back</button>
              <button className="btn btn-primary">Next</button>
            </div>

            <div className="button-group">
              <button className="btn btn-icon">
                <span>←</span>
                <span>Previous</span>
              </button>
              <button className="btn btn-icon">
                <span>Next</span>
                <span>→</span>
              </button>
            </div>
          </div>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Primary button on {isRTL ? 'left' : 'right'}, arrows point correctly</span>
          </div>
        </section>

        {/* Section 5: Icons and Logos */}
        <section className="test-section">
          <h2>5. Icons and Logos</h2>
          <p className="test-description">
            Directional icons should flip. Logos and non-directional icons should NOT flip.
          </p>
          
          <div className="icon-test-grid">
            <div className="icon-test-item">
              <div className="icon-display chevron">→</div>
              <span>Chevron (SHOULD flip)</span>
            </div>

            <div className="icon-test-item">
              <div className="icon-display arrow-icon">➜</div>
              <span>Arrow (SHOULD flip)</span>
            </div>

            <div className="icon-test-item">
              <div className="icon-display logo">🏢</div>
              <span>Logo (should NOT flip)</span>
            </div>

            <div className="icon-test-item">
              <div className="icon-display brand-icon">⭐</div>
              <span>Brand (should NOT flip)</span>
            </div>

            <div className="icon-test-item">
              <div className="icon-display avatar">👤</div>
              <span>Avatar (should NOT flip)</span>
            </div>

            <div className="icon-test-item">
              <div className="icon-display image-icon">🖼️</div>
              <span>Image (should NOT flip)</span>
            </div>
          </div>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Only directional arrows flip, logos/avatars remain unchanged</span>
          </div>
        </section>

        {/* Section 6: Dropdown Menus */}
        <section className="test-section">
          <h2>6. Dropdown Menus</h2>
          <p className="test-description">
            Dropdowns should align to the start edge and content should flow properly.
          </p>
          
          <div className="dropdown-test">
            <button 
              className="btn btn-primary"
              onClick={() => setDropdownOpen(!dropdownOpen)}
            >
              Actions ▼
            </button>
            
            {dropdownOpen && (
              <div className="dropdown-menu">
                <a href="#" className="dropdown-item">Edit</a>
                <a href="#" className="dropdown-item">Duplicate</a>
                <a href="#" className="dropdown-item">Archive</a>
                <div className="dropdown-divider"></div>
                <a href="#" className="dropdown-item danger">Delete</a>
              </div>
            )}
          </div>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Menu aligns to {isRTL ? 'right' : 'left'} edge, items align to start</span>
          </div>
        </section>

        {/* Section 7: Cards */}
        <section className="test-section">
          <h2>7. Card Layouts</h2>
          <p className="test-description">
            Card content should align to start, headers should have proper spacing.
          </p>
          
          <div className="card-test-grid">
            <div className="card">
              <div className="card-header">
                <h3>Card Title</h3>
                <button className="card-action">⋮</button>
              </div>
              <div className="card-body">
                <p>This is card content. It should align properly in both LTR and RTL modes.</p>
                <div className="card-meta">
                  <span className="badge">Status: Active</span>
                  <span className="card-date">2024-01-15</span>
                </div>
              </div>
              <div className="card-footer">
                <button className="btn btn-sm">View</button>
                <button className="btn btn-sm btn-primary">Edit</button>
              </div>
            </div>

            <div className="card">
              <div className="card-header">
                <h3>Another Card</h3>
                <button className="card-action">×</button>
              </div>
              <div className="card-body">
                <ul className="card-list">
                  <li>First item with icon</li>
                  <li>Second item with icon</li>
                  <li>Third item with icon</li>
                </ul>
              </div>
            </div>
          </div>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Content aligns to start, actions on correct side</span>
          </div>
        </section>

        {/* Section 8: Tables */}
        <section className="test-section">
          <h2>8. Table Layouts</h2>
          <p className="test-description">
            Table headers and cells should align to start. Columns should flow properly.
          </p>
          
          <div className="table-container">
            <table className="test-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Score</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>John Doe</td>
                  <td>john@example.com</td>
                  <td>85</td>
                  <td><span className="badge badge-success">Active</span></td>
                  <td>
                    <button className="btn-icon-sm">✏️</button>
                    <button className="btn-icon-sm">🗑️</button>
                  </td>
                </tr>
                <tr>
                  <td>Jane Smith</td>
                  <td>jane@example.com</td>
                  <td>92</td>
                  <td><span className="badge badge-success">Active</span></td>
                  <td>
                    <button className="btn-icon-sm">✏️</button>
                    <button className="btn-icon-sm">🗑️</button>
                  </td>
                </tr>
                <tr>
                  <td>Ahmed Ali</td>
                  <td>ahmed@example.com</td>
                  <td>78</td>
                  <td><span className="badge badge-warning">Pending</span></td>
                  <td>
                    <button className="btn-icon-sm">✏️</button>
                    <button className="btn-icon-sm">🗑️</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>All columns align to start, action buttons on correct side</span>
          </div>
        </section>

        {/* Section 9: Lists */}
        <section className="test-section">
          <h2>9. List Items</h2>
          <p className="test-description">
            List items with icons should have proper spacing and alignment.
          </p>
          
          <ul className="test-list">
            <li className="list-item">
              <span className="list-icon">✓</span>
              <span className="list-item-content">First list item with icon</span>
              <span className="list-action">→</span>
            </li>
            <li className="list-item">
              <span className="list-icon">✓</span>
              <span className="list-item-content">Second list item with icon</span>
              <span className="list-action">→</span>
            </li>
            <li className="list-item">
              <span className="list-icon">✓</span>
              <span className="list-item-content">Third list item with icon</span>
              <span className="list-action">→</span>
            </li>
          </ul>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Icons on start, actions on end, content flows properly</span>
          </div>
        </section>

        {/* Section 10: Breadcrumbs */}
        <section className="test-section">
          <h2>10. Breadcrumb Navigation</h2>
          <p className="test-description">
            Breadcrumbs should flow from start to end with proper separators.
          </p>
          
          <nav className="breadcrumb">
            <span className="breadcrumb-item">Home</span>
            <span className="breadcrumb-separator">/</span>
            <span className="breadcrumb-item">Sales</span>
            <span className="breadcrumb-separator">/</span>
            <span className="breadcrumb-item">Leads</span>
            <span className="breadcrumb-separator">/</span>
            <span className="breadcrumb-item active">John Doe</span>
          </nav>

          <div className="test-result">
            <span className="result-label">Expected:</span>
            <span>Breadcrumbs flow {isRTL ? 'right-to-left' : 'left-to-right'}</span>
          </div>
        </section>

        {/* Test Summary */}
        <section className="test-summary">
          <h2>Test Checklist</h2>
          <p>Verify the following on each browser:</p>
          <ul className="checklist">
            <li>✓ Navigation menus mirror correctly</li>
            <li>✓ Sidebar appears on correct side</li>
            <li>✓ Form layouts align properly</li>
            <li>✓ Button groups reverse order</li>
            <li>✓ Directional icons flip, logos don't</li>
            <li>✓ Dropdown menus align correctly</li>
            <li>✓ Card layouts flow properly</li>
            <li>✓ Tables align to start</li>
            <li>✓ List items have correct spacing</li>
            <li>✓ Breadcrumbs flow correctly</li>
          </ul>
        </section>
      </div>
    </div>
  );
};
