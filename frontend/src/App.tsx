import React from 'react'
import { ThemeProvider } from "./components/theme-provider"
import { Layout } from "./components/layout"
import { Dashboard } from "./modules/dashboard/dashboard"

function App() {
  return (
    <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
      <Layout>
        <Dashboard />
      </Layout>
    </ThemeProvider>
  )
}

export default App
