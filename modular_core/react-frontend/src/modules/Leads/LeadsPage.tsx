import React, { useState, useEffect } from "react"
import { useAuth, Can } from "../../core/AuthContext"
import { Button } from "../../components/ui/Button"
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../../components/ui/Card"
import { Input } from "../../components/ui/Input"
import { Skeleton, TableSkeleton } from "../../components/ui/Skeleton"
import { cn } from "../../lib/utils"

interface Lead {
  id: number
  first_name: string
  last_name: string
  email: string
  source?: string
  ai_score?: number
  lifecycle_stage: string
  created_at: string
  company_name?: string
}

/**
 * Enterprise Leads Management (v2)
 * Full Alignment: TypeScript + Tailwind + shadcn/ui + Glassmorphism
 */
export default function LeadsPage() {
  const [leads, setLeads] = useState<Lead[]>([])
  const [loading, setLoading] = useState(true)
  const [searchTerm, setSearchTerm] = useState("")

  useEffect(() => {
    const token = localStorage.getItem("access_token")
    fetch("/api/leads", {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setLeads(d.data)
        setLoading(false)
      })
      .catch(() => setLoading(false))
  }, [])

  const filteredLeads = leads.filter(
    (lead) =>
      (lead.first_name + " " + lead.last_name).toLowerCase().includes(searchTerm.toLowerCase()) ||
      lead.email.toLowerCase().includes(searchTerm.toLowerCase())
  )

  return (
    <div className="flex flex-col gap-8 p-8 max-w-[1400px] mx-auto animate-in fade-in duration-500">
      <header className="flex justify-between items-start">
        <div className="space-y-1">
          <h1 className="text-4xl font-extrabold tracking-tight text-white lg:text-5xl">
            Leads Management
          </h1>
          <p className="text-slate-400 text-lg">
            Manage and track your omnichannel sales prospects with AI intelligence.
          </p>
        </div>
        <div className="flex gap-3">
          <Can module="leads" action="import">
            <Button variant="outline" className="text-white border-white/10 hover:bg-white/5">
              📥 Import CSV
            </Button>
          </Can>
          <Can module="leads" action="create">
            <Button className="bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/20">
              + Add New Lead
            </Button>
          </Can>
        </div>
      </header>

      <Card className="border-white/10 bg-slate-900/40 backdrop-blur-xl">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-7">
          <div className="space-y-1">
            <CardTitle className="text-xl font-bold">Prospect Pipeline</CardTitle>
            <CardDescription>Live feed of your incoming leads and AI readiness scores.</CardDescription>
          </div>
          <div className="relative w-72">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 opacity-50">🔍</span>
            <Input
              placeholder="Filter by name or email..."
              className="pl-10"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <TableSkeleton rows={8} />
          ) : (
            <div className="relative overflow-x-auto rounded-lg border border-white/5">
              <table className="w-full text-left text-sm">
                <thead className="bg-white/5 text-slate-400 uppercase text-[10px] font-bold tracking-widest">
                  <tr>
                    <th className="px-6 py-4">Name & Contact</th>
                    <th className="px-6 py-4">Source</th>
                    <th className="px-6 py-4">Score</th>
                    <th className="px-6 py-4">Stage</th>
                    <th className="px-6 py-4">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {filteredLeads.map((lead) => (
                    <tr key={lead.id} className="hover:bg-white/5 transition-colors group">
                      <td className="px-6 py-4 bg-transparent">
                        <div className="flex flex-col">
                          <span className="font-semibold text-slate-100 uppercase tracking-tight">
                            {lead.first_name} {lead.last_name}
                          </span>
                          <span className="text-xs text-slate-500">{lead.email}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-400 border border-blue-500/20">
                          {lead.source || "Direct"}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <div
                          className={cn(
                            "flex h-9 w-9 items-center justify-center rounded-full text-[10px] font-black text-white shadow-lg",
                            getScoreColorClass(lead.ai_score || 0)
                          )}
                        >
                          {lead.ai_score || 0}%
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-xs font-semibold text-slate-300 bg-white/5 px-3 py-1 rounded-full">
                          {lead.lifecycle_stage}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex gap-2">
                          <Button 
                            variant="ghost" 
                            size="icon" 
                            className="h-8 w-8 text-slate-400 hover:text-white"
                          >
                            ✏️
                          </Button>
                          <Button 
                            variant="ghost" 
                            size="icon" 
                            className="h-8 w-8 text-blue-400 hover:bg-blue-500/10"
                            onClick={() => alert(`🚀 AI Copilot drafting for ${lead.first_name}...`)}
                          >
                            🤖
                          </Button>
                          <Button 
                            variant="ghost" 
                            size="icon" 
                            className="h-8 w-8 text-red-400 hover:bg-red-500/10"
                          >
                            🗑️
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {!loading && filteredLeads.length === 0 && (
            <div className="py-20 text-center text-slate-500">
              <p>No matches found in your lead database.</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function getScoreColorClass(score: number): string {
  if (score >= 80) return "bg-emerald-500 shadow-emerald-500/40"
  if (score >= 50) return "bg-amber-500 shadow-amber-500/40"
  return "bg-rose-500 shadow-rose-500/40"
}
