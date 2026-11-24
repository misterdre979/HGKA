import { useState, type ReactNode } from 'react'
import { ResourceTable } from '@/components/ResourceTable'
import { PageHeader, Tabs } from '@/components/ui'
import { RESOURCES } from '@/config/resources.generated'
import { useAuth } from '@/context/AuthContext'

export interface TabSpec { resource?: string; id?: string; label?: string; render?: () => ReactNode; perms?: string[] }

/** A page of tabs; each tab is a generic resource table or custom content. Tabs the user cannot see are hidden. */
export function ResourceTabsPage({ title, subtitle, tabs, noCreate }: { title: string; subtitle?: string; tabs: TabSpec[]; noCreate?: string[] }) {
  const { can } = useAuth()
  const visible = tabs.filter((t) => (t.perms ? can(...t.perms) : t.resource ? can(...(RESOURCES[t.resource]?.perms.view ?? [])) : true))
  const [active, setActive] = useState(0)
  const cur = visible[Math.min(active, visible.length - 1)]
  if (!cur) return <p className="text-sm text-slate-500">You do not have access to anything on this page.</p>
  return (
    <>
      <PageHeader title={title} subtitle={subtitle} />
      {visible.length > 1 && (
        <Tabs
          tabs={visible.map((t, i) => ({ id: String(i), label: t.label ?? RESOURCES[t.resource ?? '']?.label ?? 'Tab' }))}
          value={String(Math.min(active, visible.length - 1))} onChange={(v) => setActive(Number(v))}
        />
      )}
      <div key={cur.resource ?? cur.id}>
        {cur.render ? cur.render() : <ResourceTable resource={cur.resource!} hideHeader noCreate={noCreate?.includes(cur.resource!)} />}
      </div>
    </>
  )
}
