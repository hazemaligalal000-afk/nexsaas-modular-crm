import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './NexSaaSTheme.css'

/**
 * Enterprise NexSaaS Entry Point
 * Requirement: 1.28 - TypeScript + Vite
 */
ReactDOM.createRoot(document.getElementById('root') as HTMLElement).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
