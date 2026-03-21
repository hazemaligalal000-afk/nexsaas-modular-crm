/**
 * RTL Test Component
 * Visual test component to verify RTL layout support
 * Requirements: 11
 */

import React from 'react';
import { useDirection, useIsRTL } from '../i18n/hooks';
import './RTLTest.css';

export const RTLTest: React.FC = () => {
  const direction = useDirection();
  const isRTL = useIsRTL();

  return (
    <div className="rtl-test-container">
      <h2>RTL Layout Test</h2>
      
      <div className="test-info">
        <p><strong>Current Direction:</strong> {direction}</p>
        <p><strong>Is RTL:</strong> {isRTL ? 'Yes' : 'No'}</p>
        <p><strong>HTML dir attribute:</strong> {document.documentElement.getAttribute('dir')}</p>
      </div>

      <div className="test-section">
        <h3>Text Alignment</h3>
        <p className="text-start">This text should align to the start (left in LTR, right in RTL)</p>
        <p className="text-end">This text should align to the end (right in LTR, left in RTL)</p>
      </div>

      <div className="test-section">
        <h3>Spacing (Logical Properties)</h3>
        <div className="spacing-test">
          <div className="box m-inline-start-4">margin-inline-start</div>
          <div className="box m-inline-end-4">margin-inline-end</div>
          <div className="box p-inline-start-4">padding-inline-start</div>
          <div className="box p-inline-end-4">padding-inline-end</div>
        </div>
      </div>

      <div className="test-section">
        <h3>Borders</h3>
        <div className="border-test">
          <div className="box border-inline-start">border-inline-start</div>
          <div className="box border-inline-end">border-inline-end</div>
        </div>
      </div>

      <div className="test-section">
        <h3>Flexbox Layout</h3>
        <div className="flex-test">
          <button className="btn-with-icon">
            <span>←</span>
            <span>Icon Start</span>
          </button>
          <button className="btn-with-icon">
            <span>Button Text</span>
            <span>→</span>
          </button>
        </div>
      </div>

      <div className="test-section">
        <h3>List Items</h3>
        <ul className="list-test">
          <li className="list-item">
            <span className="list-icon">●</span>
            <span className="list-item-content">First item</span>
          </li>
          <li className="list-item">
            <span className="list-icon">●</span>
            <span className="list-item-content">Second item</span>
          </li>
          <li className="list-item">
            <span className="list-icon">●</span>
            <span className="list-item-content">Third item</span>
          </li>
        </ul>
      </div>

      <div className="test-section">
        <h3>Form Elements</h3>
        <form className="form-test">
          <div className="form-field">
            <label className="form-label">Name:</label>
            <input type="text" className="form-input" placeholder="Enter your name" />
          </div>
          <div className="form-field">
            <label className="form-label">Email:</label>
            <input type="email" className="form-input" placeholder="email@example.com" />
          </div>
          <div className="form-field">
            <label className="form-label">Phone:</label>
            <input type="tel" className="form-input" placeholder="+1234567890" />
          </div>
        </form>
      </div>

      <div className="test-section">
        <h3>Icons (Directional vs Non-directional)</h3>
        <div className="icon-test">
          <div className="icon-group">
            <span className="chevron">→</span>
            <span>Chevron (should flip)</span>
          </div>
          <div className="icon-group">
            <span className="logo">🏢</span>
            <span>Logo (should NOT flip)</span>
          </div>
          <div className="icon-group">
            <span className="avatar">👤</span>
            <span>Avatar (should NOT flip)</span>
          </div>
        </div>
      </div>

      <div className="test-section">
        <h3>Card Layout</h3>
        <div className="card">
          <div className="card-header">
            <h4>Card Title</h4>
            <button>×</button>
          </div>
          <div className="card-body">
            <p>This is the card body content. It should align properly in both LTR and RTL modes.</p>
          </div>
          <div className="card-footer">
            <button>Cancel</button>
            <button>Save</button>
          </div>
        </div>
      </div>
    </div>
  );
};
