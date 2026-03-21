import React from 'react'
import { ThemeProvider } from "./components/theme-provider"
import { Layout } from "./components/layout"
import { AccountingDashboard } from "./modules/Accounting/AccountingDashboard"
import { I18nProvider } from "./context/I18nContext"

function App() {
  return (
    <I18nProvider>
      <ThemeProvider defaultTheme="light" storageKey="vite-ui-theme">
        <Layout>
          <AccountingDashboard />
        </Layout>
      </ThemeProvider>
    </I18nProvider>
  )
}

export default App
