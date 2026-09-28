import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import './App.css'

type Membership = { id: string; branchId: string; branchName: string; role: string }
type Session = {
  name: string; email: string; hospitalName: string; membershipId: string
  branchName: string; role: string; permissions: string[]; memberships: Membership[]
}

const api = import.meta.env.VITE_API_BASE_URL ?? '/api'

async function csrf(): Promise<{ headerName: string; token: string }> {
  const response = await fetch(`${api}/v1/auth/csrf`, { credentials: 'include' })
  if (!response.ok) throw new Error('Unable to establish a secure session.')
  return response.json()
}

async function mutate(path: string, body?: unknown): Promise<Response> {
  const token = await csrf()
  return fetch(`${api}${path}`, {
    method: 'POST', credentials: 'include',
    headers: { 'Content-Type': 'application/json', [token.headerName]: token.token },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
}

function App() {
  const [session, setSession] = useState<Session | null>(null)
  const [checking, setChecking] = useState(true)
  const [email, setEmail] = useState('reception@lotus.test')
  const [password, setPassword] = useState('CareDesk@2026!')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    fetch(`${api}/v1/me`, { credentials: 'include' })
      .then(async response => response.ok ? setSession(await response.json()) : setSession(null))
      .finally(() => setChecking(false))
  }, [])

  async function login(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try {
      const response = await mutate('/v1/auth/login', { email, password })
      if (!response.ok) throw new Error(response.status === 401 ? 'Email or password is incorrect.' : 'Sign in failed.')
      setSession(await response.json())
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Sign in failed.') }
    finally { setBusy(false) }
  }

  async function switchBranch(membershipId: string) {
    setBusy(true); setError('')
    try {
      const response = await mutate('/v1/context', { membershipId })
      if (!response.ok) throw new Error('That branch is not assigned to your account.')
      setSession(await response.json())
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Branch switch failed.') }
    finally { setBusy(false) }
  }

  async function logout() {
    setBusy(true)
    await mutate('/v1/auth/logout')
    setSession(null); setBusy(false)
  }

  if (checking) return <main className="loading" role="status">Opening CareDesk…</main>

  if (!session) return (
    <main className="auth-shell">
      <section className="auth-story">
        <a className="brand" href="/" aria-label="CareDesk home"><span className="brand-mark">+</span><span><strong>CareDesk</strong><small>Hospital ERP</small></span></a>
        <div><p className="eyebrow">Secure hospital workspace</p><h1>Care starts with the right context.</h1><p>Sign in to your assigned hospital and branch. Every request remains scoped and auditable.</p></div>
        <small>React · Spring Boot · MongoDB</small>
      </section>
      <section className="login-panel">
        <form onSubmit={login}>
          <p className="eyebrow">Welcome back</p><h2>Sign in to CareDesk</h2>
          <label>Email address<input type="email" value={email} onChange={event => setEmail(event.target.value)} autoComplete="username" required /></label>
          <label>Password<input type="password" value={password} onChange={event => setPassword(event.target.value)} autoComplete="current-password" required /></label>
          {error && <p className="error" role="alert">{error}</p>}
          <button disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
          <p className="demo-note">Local demo: reception@lotus.test / CareDesk@2026!</p>
        </form>
      </section>
    </main>
  )

  return (
    <main className="portal-shell">
      <aside>
        <a className="brand brand--light" href="/"><span className="brand-mark">+</span><span><strong>CareDesk</strong><small>Hospital ERP</small></span></a>
        <nav><a className="active" href="#overview">Overview</a><a href="#patients">Patients</a><a href="#appointments">Appointments</a>{session.permissions.includes('AUDIT.VIEW') && <a href="#audit">Audit log</a>}</nav>
        <button className="quiet" onClick={logout} disabled={busy}>Sign out</button>
      </aside>
      <section className="workspace">
        <header><div><p className="eyebrow">{session.hospitalName}</p><h1>Good day, {session.name.split(' ')[0]}.</h1></div><div className="user-chip"><strong>{session.name}</strong><span>{session.role.replaceAll('_', ' ')}</span></div></header>
        <section className="context-card"><div><small>Active care location</small><strong>{session.branchName}</strong></div><label>Switch assigned branch<select value={session.membershipId} onChange={event => switchBranch(event.target.value)} disabled={busy}>{session.memberships.map(item => <option key={item.id} value={item.id}>{item.branchName} · {item.role.replaceAll('_', ' ')}</option>)}</select></label></section>
        {error && <p className="error" role="alert">{error}</p>}
        <section className="welcome-grid" id="overview"><article><span>Identity</span><strong>Authenticated</strong><p>Server-side session with CSRF protection.</p></article><article><span>Tenant</span><strong>{session.branchName}</strong><p>Requests are restricted to this membership.</p></article><article><span>Permissions</span><strong>{session.permissions.length}</strong><p>Role permissions loaded by the API.</p></article></section>
        <section className="next-card"><p className="eyebrow">Foundation ready</p><h2>Identity and tenant isolation are active.</h2><p>Patient, doctor, availability, and reception appointment workflows are next in the migration sequence.</p></section>
      </section>
    </main>
  )
}

export default App
