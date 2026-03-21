import React, { useState, useEffect } from "react"
import { useAuth } from "../../core/AuthContext"
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../../components/ui/Card"
import { Button } from "../../components/ui/Button"
import { Skeleton } from "../../components/ui/Skeleton"
import { cn } from "../../lib/utils"

interface KPI {
  label: string
  value: string
  trend: string
  icon: string
}

interface PipelineStage {
  lifecycle_stage: string
  cnt: number
}

interface AnalyticsData {
  kpis: KPI[]
  pipeline_breakdown: PipelineStage[]
}

/**
 * Enterprise NexSaaS Admin Dashboard (v2)
 * Full Alignment: TypeScript + Tailwind + shadcn/ui + Glassmorphism
 */
export default function AdminDashboard() {
  const { user } = useAuth()
  const [data, setData] = useState<AnalyticsData | null>(null)

  useEffect(() => {
    const token = localStorage.getItem("access_token")
    fetch("/api/analytics/overview", { headers: { Authorization: `Bearer ${token}` } })
      .then((r) => r.json())
      .then((d) => setData(d.data))
      .catch(() => {})
  }, [])

  if (!data) {
    return (
      <div className="p-8 space-y-8 animate-pulse">
        <div className="h-8 w-64 bg-slate-800 rounded-lg" />
        <div className="grid grid-cols-5 gap-4">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-32 bg-slate-900/50 rounded-xl border border-white/5" />
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="p-8 space-y-8 max-w-[1600px] mx-auto animate-in fade-in zoom-in-95 duration-700">
      {/* Dynamic Header */}
      <header className="flex justify-between items-end">
        <div className="space-y-2">
          <div className="flex items-center gap-3">
            <span className="text-4xl">🛡️</span>
            <h1 className="text-4xl font-black tracking-tighter text-white">System Command</h1>
            <span className="bg-blue-500/10 text-blue-400 text-[10px] font-bold px-3 py-1 rounded-full border border-blue-500/20 tracking-widest uppercase">
              Elite Admin
            </span>
          </div>
          <p className="text-slate-400 font-medium">
            Welcome back, <span className="text-white font-bold">{user?.name}</span>. Platform integrity is nominal.
          </p>
        </div>
        <div className="flex gap-3">
          <Button variant="outline" className="border-white/10 hover:bg-white/5 text-slate-300">
            📊 Generate Audit
          </Button>
          <Button className="bg-blue-600 hover:bg-blue-500 text-white shadow-xl shadow-blue-600/20">
            ⚡ Platform Live
          </Button>
        </div>
      </header>

      {/* KPI Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
        {data.kpis.map((k, i) => (
          <KpiCard key={i} kpi={k} index={i} />
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Pipeline Intelligence */}
        <Card className="lg:col-span-8 bg-slate-900/40 backdrop-blur-3xl border-white/10">
          <CardHeader>
            <CardTitle className="text-xl font-bold flex items-center gap-2">
              <span className="h-2 w-2 rounded-full bg-blue-500 animate-pulse" />
              Pipeline Intelligence
            </CardTitle>
            <CardDescription>Real-time conversion distribution across the tenant network.</CardDescription>
          </CardHeader>
          <CardContent>
            <PipelineGraph stages={data.pipeline_breakdown} />
          </CardContent>
        </Card>

        {/* Activity Feed */}
        <Card className="lg:col-span-4 bg-slate-900/40 backdrop-blur-3xl border-white/10">
          <CardHeader>
            <CardTitle className="text-xl font-bold">Recent Signals</CardTitle>
            <CardDescription>Live system events and actor logs.</CardDescription>
          </CardHeader>
          <CardContent>
            <ActivityFeed />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function KpiCard({ kpi, index }: { kpi: KPI; index: number }) {
  const colors = ["bg-blue-500", "bg-emerald-500", "bg-purple-500", "bg-amber-500", "bg-rose-500"]
  const isUp = !kpi.trend.startsWith("-") && kpi.trend !== "0"

  return (
    <Card className="group hover:-translate-y-1 transition-all duration-300 overflow-hidden relative">
      <div className={cn("absolute top-0 left-0 w-1 h-full", colors[index % colors.length])} />
      <CardHeader className="pb-2">
        <div className="flex justify-between items-start">
          <div className={cn("p-2 rounded-lg bg-white/5 text-xl", colors[index % colors.length])}>
            {kpi.icon}
          </div>
          <span className={cn(
            "text-[10px] font-black px-2 py-0.5 rounded-full",
            isUp ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20" : "bg-rose-500/10 text-rose-400 border border-rose-500/20"
          )}>
            {kpi.trend}
          </span>
        </div>
      </CardHeader>
      <CardContent>
        <div className="text-3xl font-black tracking-tighter text-white font-mono">{kpi.value}</div>
        <p className="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">{kpi.label}</p>
      </CardContent>
    </Card>
  )
}

function PipelineGraph({ stages }: { stages: PipelineStage[] }) {
  const colors = ["bg-blue-400", "bg-emerald-400", "bg-purple-400", "bg-amber-400", "bg-rose-400", "bg-cyan-400"]
  const total = stages.reduce((acc, s) => acc + s.cnt, 0)

  return (
    <div className="space-y-5">
      {stages.map((s, i) => {
        const pct = Math.round((s.cnt / total) * 100)
        return (
          <div key={i} className="space-y-2">
            <div className="flex justify-between text-xs font-bold uppercase tracking-tight">
              <span className="text-slate-400">{s.lifecycle_stage}</span>
              <div className="flex gap-2 items-center">
                <span className="text-slate-600 text-[10px]">{pct}%</span>
                <span className="text-white">{s.cnt}</span>
              </div>
            </div>
            <div className="h-2 w-full bg-white/5 rounded-full overflow-hidden">
              <div 
                className={cn("h-full rounded-full transition-all duration-1000", colors[i % colors.length])} 
                style={{ width: `${pct}%` }} 
              />
            </div>
          </div>
        )
      })}
    </div>
  )
}

function ActivityFeed() {
  const events = [
    { icon: "👤", text: "Elite Lead captured: Sarah M. (Score: 92)", time: "2m", color: "text-blue-400" },
    { icon: "💰", text: "Deal Projected: Amazon Enterprise 1.2M", time: "15m", color: "text-emerald-400" },
    { icon: "🤖", text: "AI Agent optimized 14 outreach emails", time: "44m", color: "text-purple-400" },
    { icon: "🛡️", text: "SAML SSO Provider sync completed", time: "1h", color: "text-amber-400" },
  ]

  return (
    <div className="space-y-4">
      {events.map((e, i) => (
        <div key={i} className="flex gap-4 items-center group cursor-default">
          <div className="h-10 w-10 rounded-xl bg-white/5 flex items-center justify-center text-lg border border-white/5 group-hover:bg-white/10 transition-colors">
            {e.icon}
          </div>
          <div className="flex-1 space-y-0.5">
            <p className={cn("text-xs font-semibold tracking-tight", e.color)}>{e.text}</p>
            <p className="text-[10px] text-slate-600 font-bold uppercase tracking-widest">{e.time} ago</p>
          </div>
        </div>
      ))}
      <Button variant="ghost" className="w-full text-xs text-slate-500 hover:text-white mt-4">
        Discover Trace Logs →
      </Button>
    </div>
  )
}
