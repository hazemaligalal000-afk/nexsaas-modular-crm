import React, { useState, useEffect, useRef } from "react"
import { Button } from "../../components/ui/Button"
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../../components/ui/Card"
import { Input } from "../../components/ui/Input"
import { cn } from "../../lib/utils"

interface Conversation {
  id: number
  channel: string
  name: string
  phone?: string
  email?: string
  avatar: string
  status: "open" | "pending" | "resolved" | "spam"
  unread: number
  lastMsg: string
  time: string
  assignee: string
}

interface Message {
  id: number
  from: "contact" | "agent"
  text: string
  time: string
  channel?: string
  agent?: string
}

const CHANNELS = [
  { id: "all", label: "All", icon: "🗂️", color: "text-slate-400", bg: "bg-slate-500/10" },
  { id: "whatsapp", label: "WhatsApp", icon: "💚", color: "text-emerald-400", bg: "bg-emerald-500/10" },
  { id: "telegram", label: "Telegram", icon: "✈️", color: "text-sky-400", bg: "bg-sky-500/10" },
  { id: "email", label: "Email", icon: "📧", color: "text-blue-400", bg: "bg-blue-500/10" },
  { id: "sms", label: "SMS", icon: "📱", color: "text-purple-400", bg: "bg-purple-500/10" },
  { id: "livechat", label: "Live Chat", icon: "💬", color: "text-amber-400", bg: "bg-amber-500/10" },
]

/**
 * Elite Omnichannel Inbox (v2)
 * Full Alignment: TypeScript + Tailwind + shadcn/ui + Glassmorphism
 */
export default function OmnichannelInbox() {
  const [activeChannel, setActiveChannel] = useState("all")
  const [selectedConv, setSelectedConv] = useState<Conversation | null>(null)
  const [reply, setReply] = useState("")
  const [search, setSearch] = useState("")
  const scrollRef = useRef<HTMLDivElement>(null)

  // Demo Data (In production, this would be fetched via React Query/SWR)
  const [conversations] = useState<Conversation[]>([
    { id: 1, channel: "whatsapp", name: "Ahmed Hassan", phone: "+201001234567", avatar: "AH", status: "open", unread: 3, lastMsg: "Double charge issue on INV-2026", time: "2m", assignee: "Sara M." },
    { id: 2, channel: "email", name: "Fatima Al-Zahra", email: "fatima@tech.com", avatar: "FZ", status: "open", unread: 1, lastMsg: "RE: Demo Request schedule", time: "8m", assignee: "Omar K." },
    { id: 3, channel: "livechat", name: "Maria G.", avatar: "MG", status: "open", unread: 0, lastMsg: "How do I upgrade to Enterprise?", time: "14m", assignee: "Unassigned" },
  ])

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [selectedConv])

  const filtered = conversations.filter(c => 
    (activeChannel === "all" || c.channel === activeChannel) &&
    (c.name.toLowerCase().includes(search.toLowerCase()) || c.lastMsg.toLowerCase().includes(search.toLowerCase()))
  )

  return (
    <div className="flex h-[calc(100vh-100px)] gap-1 animate-in fade-in duration-700 bg-slate-950/40 rounded-3xl overflow-hidden border border-white/5">
      
      {/* Channel Sidebar */}
      <nav className="w-16 flex flex-col items-center py-6 gap-3 bg-slate-900/60 border-r border-white/5">
        {CHANNELS.map(ch => (
          <button
            key={ch.id}
            onClick={() => setActiveChannel(ch.id)}
            className={cn(
              "w-11 h-11 rounded-xl flex items-center justify-center text-xl transition-all relative group",
              activeChannel === ch.id ? "bg-blue-600 shadow-lg shadow-blue-600/20" : "hover:bg-white/5"
            )}
          >
            {ch.icon}
            <span className="absolute left-full ml-4 px-2 py-1 bg-slate-900 rounded text-[10px] font-bold invisible group-hover:visible whitespace-nowrap z-50">
              {ch.label}
            </span>
          </button>
        ))}
      </nav>

      {/* Conv List */}
      <aside className="w-80 flex flex-col bg-slate-900/30 border-r border-white/5">
        <div className="p-4 space-y-4">
          <div className="flex justify-between items-center px-1">
            <h2 className="text-sm font-black uppercase tracking-widest text-slate-500">Inbox</h2>
            <span className="glass-pill text-[10px]">{filtered.length} active</span>
          </div>
          <Input 
            placeholder="Search signals..." 
            className="h-9 text-xs"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <div className="flex-1 overflow-y-auto custom-scrollbar">
          {filtered.map(conv => (
            <div 
              key={conv.id}
              onClick={() => setSelectedConv(conv)}
              className={cn(
                "p-4 cursor-pointer transition-all border-l-2",
                selectedConv?.id === conv.id ? "bg-blue-600/10 border-blue-500" : "hover:bg-white/5 border-transparent"
              )}
            >
              <div className="flex gap-3">
                <div className="h-10 w-10 rounded-xl bg-white/5 flex items-center justify-center font-bold text-xs border border-white/5 relative">
                  {conv.avatar}
                  <span className="absolute -bottom-1 -right-1 text-[10px]">{CHANNELS.find(x => x.id === conv.channel)?.icon}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex justify-between items-center mb-0.5">
                    <span className="text-sm font-bold text-slate-100 truncate">{conv.name}</span>
                    <span className="text-[10px] text-slate-500 font-mono">{conv.time}</span>
                  </div>
                  <p className="text-xs text-slate-400 truncate leading-relaxed">{conv.lastMsg}</p>
                </div>
              </div>
            </div>
          ))}
        </div>
      </aside>

      {/* Chat Space */}
      <main className="flex-1 flex flex-col bg-slate-950/20 backdrop-blur-sm relative">
        {selectedConv ? (
          <>
            {/* Header */}
            <header className="h-16 flex items-center justify-between px-6 border-bottom border-white/5 bg-slate-900/40 backdrop-blur-xl">
              <div className="flex items-center gap-4">
                <div className="h-9 w-9 bg-white/5 rounded-lg flex items-center justify-center font-black">
                  {selectedConv.avatar}
                </div>
                <div>
                  <h3 className="text-sm font-bold text-white leading-none">{selectedConv.name}</h3>
                  <p className="text-[10px] text-slate-500 mt-1 uppercase font-bold tracking-tighter">
                    {selectedConv.channel} · {selectedConv.assignee}
                  </p>
                </div>
              </div>
              <div className="flex gap-2">
                <Button variant="ghost" size="sm" className="text-xs hover:bg-emerald-500/10 hover:text-emerald-400">
                  ✓ Resolve
                </Button>
                <Button variant="ghost" size="icon" className="h-8 w-8">⋯</Button>
              </div>
            </header>

            {/* Messages */}
            <div ref={scrollRef} className="flex-1 overflow-y-auto p-8 space-y-6">
              <MessageBubble text={selectedConv.lastMsg} time="10:02 AM" isAgent={false} name={selectedConv.name} />
              <MessageBubble text="Hi! Looking into your billing right now. One moment." time="10:05 AM" isAgent={true} name="You" />
              <div className="flex justify-center">
                <span className="text-[10px] text-slate-700 font-bold uppercase tracking-widest bg-white/5 px-3 py-1 rounded-full border border-white/5">
                  AI Suggestion Tier active
                </span>
              </div>
            </div>

            {/* Compose */}
            <footer className="p-6 bg-slate-900/60 border-t border-white/5 space-y-4">
              <div className="flex gap-2">
                <span className="text-[10px] font-bold text-blue-400 cursor-pointer">Reply</span>
                <span className="text-[10px] font-bold text-slate-500 cursor-pointer hover:text-slate-300 transition-colors">Internal Note</span>
              </div>
              <div className="flex gap-3 items-end">
                <div className="flex-1 bg-slate-950/50 border border-white/10 rounded-2xl overflow-hidden focus-within:border-blue-500/50 transition-all">
                  <textarea 
                    className="w-full bg-transparent border-none p-4 text-sm text-white resize-none outline-none placeholder:text-slate-600"
                    placeholder={`Reply to ${selectedConv.name}...`}
                    rows={2}
                    value={reply}
                    onChange={(e) => setReply(e.target.value)}
                  />
                  <div className="px-4 pb-3 flex gap-4 text-slate-500">
                    <button className="hover:text-blue-400 transition-colors text-lg">📎</button>
                    <button className="hover:text-blue-400 transition-colors text-lg">📷</button>
                    <button className="hover:text-blue-400 transition-colors text-lg">😊</button>
                  </div>
                </div>
                <div className="flex flex-col gap-2">
                  <Button size="icon" className="h-10 w-10 bg-blue-600/10 text-blue-400 border border-blue-500/20 hover:bg-blue-600 hover:text-white transition-all">
                    🤖
                  </Button>
                  <Button size="icon" className="h-10 w-10 bg-blue-600 shadow-lg shadow-blue-500/20">
                    ↑
                  </Button>
                </div>
              </div>
            </footer>
          </>
        ) : (
          <div className="flex-1 flex items-center justify-center flex-col gap-4 opacity-20">
            <span className="text-8xl">🗂️</span>
            <p className="text-lg font-bold tracking-tighter italic uppercase text-white">Transmission Standby</p>
          </div>
        )}
      </main>

      {/* Profile Sidebar */}
      {selectedConv && (
        <aside className="w-72 bg-slate-900/60 p-6 space-y-8 animate-in slide-in-from-right duration-500">
          <section className="text-center space-y-4">
            <div className="h-20 w-20 mx-auto rounded-3xl bg-blue-600 shadow-2xl flex items-center justify-center text-3xl font-black">
              {selectedConv.avatar}
            </div>
            <div>
              <h4 className="text-lg font-black text-white">{selectedConv.name}</h4>
              <p className="text-xs text-slate-500">{selectedConv.email || selectedConv.phone}</p>
            </div>
          </section>

          <section className="grid grid-cols-2 gap-2">
            <ActionButton icon="📞" label="Call" />
            <ActionButton icon="📧" label="Email" />
            <ActionButton icon="📅" label="Meet" />
            <ActionButton icon="👤" label="CRM" />
          </section>

          <section className="space-y-4">
            <h5 className="text-[10px] font-black uppercase text-slate-600 tracking-widest">Metadata</h5>
            <div className="space-y-3">
              <MetadataRow label="CSAT" value="4.9 ⭐" />
              <MetadataRow label="Lifecycle" value="Enterprise" />
              <MetadataRow label="Avg Response" value="4m 12s" />
            </div>
          </section>

          <section className="space-y-3">
             <h5 className="text-[10px] font-black uppercase text-slate-600 tracking-widest">Tags</h5>
             <div className="flex flex-wrap gap-2">
               {["VIP", "Billing", "Expansion"].map(t => (
                 <span key={t} className="text-[9px] font-black uppercase border border-white/10 bg-white/5 px-2.5 py-1 rounded-md text-blue-400">
                   {t}
                 </span>
               ))}
             </div>
          </section>
        </aside>
      )}
    </div>
  )
}

function MessageBubble({ text, time, isAgent, name }: { text: string; time: string; isAgent: boolean; name: string }) {
  return (
    <div className={cn("flex gap-3 max-w-[80%]", isAgent ? "ml-auto flex-row-reverse" : "")}>
      <div className="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center text-[10px] font-black border border-white/5 flex-shrink-0">
        {name.slice(0, 2).toUpperCase()}
      </div>
      <div className="space-y-1">
        <div className={cn(
          "p-4 text-sm leading-relaxed rounded-2xl shadow-xl",
          isAgent ? "bg-blue-600 text-white rounded-tr-none" : "bg-white/5 text-slate-200 border border-white/10 rounded-tl-none backdrop-blur-md"
        )}>
          {text}
        </div>
        <p className={cn("text-[9px] font-bold text-slate-600 uppercase tracking-widest", isAgent ? "text-right" : "")}>
          {time}
        </p>
      </div>
    </div>
  )
}

function ActionButton({ icon, label }: { icon: string; label: string }) {
  return (
    <button className="flex flex-col items-center justify-center gap-1 p-3 rounded-xl bg-white/5 border border-white/5 hover:bg-white/10 hover:border-white/20 transition-all group">
      <span className="text-xl group-hover:scale-110 transition-transform">{icon}</span>
      <span className="text-[10px] font-bold text-slate-500 uppercase">{label}</span>
    </button>
  )
}

function MetadataRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between items-center text-xs">
      <span className="text-slate-500 font-medium">{label}</span>
      <span className="text-slate-100 font-bold">{value}</span>
    </div>
  )
}
