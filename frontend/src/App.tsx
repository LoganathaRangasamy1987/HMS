import { useEffect, useState } from 'react'
import './App.css'

type ApiStatus = 'checking' | 'ready' | 'unavailable'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? '/api'

function App() {
  const [apiStatus, setApiStatus] = useState<ApiStatus>('checking')

  useEffect(() => {
    const controller = new AbortController()

    fetch(`${apiBaseUrl}/actuator/health`, { signal: controller.signal })
      .then((response) => {
        if (!response.ok) {
          throw new Error('API health check failed')
        }

        return response.json() as Promise<{ status?: string }>
      })
      .then((health) => setApiStatus(health.status === 'UP' ? 'ready' : 'unavailable'))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') {
          return
        }

        setApiStatus('unavailable')
      })

    return () => controller.abort()
  }, [])

  return (
    <main className="app-shell">
      <header className="topbar">
        <a className="brand" href="/" aria-label="CareDesk home">
          <span className="brand-mark" aria-hidden="true">+</span>
          <span>
            <strong>CareDesk</strong>
            <small>Hospital ERP</small>
          </span>
        </a>
        <span className={`status status--${apiStatus}`} role="status">
          <span aria-hidden="true" />
          {apiStatus === 'checking' && 'Checking local API'}
          {apiStatus === 'ready' && 'Local API ready'}
          {apiStatus === 'unavailable' && 'Local API unavailable'}
        </span>
      </header>

      <section className="hero-panel">
        <div>
          <p className="eyebrow">Technology migration</p>
          <h1>The new CareDesk workspace is running.</h1>
          <p className="lede">
            React now provides the user interface, Spring Boot provides the API,
            and MongoDB is the local data platform. The verified Laravel system
            remains available as the behavior reference during migration.
          </p>
        </div>
        <div className="stack-card" aria-label="Application technology stack">
          <span>Frontend<strong>React + TypeScript</strong></span>
          <span>Backend<strong>Spring Boot</strong></span>
          <span>Database<strong>MongoDB</strong></span>
        </div>
      </section>

      <section className="roadmap" aria-labelledby="roadmap-title">
        <div>
          <p className="eyebrow">Migration sequence</p>
          <h2 id="roadmap-title">Foundation before feature parity</h2>
        </div>
        <ol>
          <li className="complete"><span>01</span><div><strong>Workspace</strong><small>Scaffold and health checks</small></div></li>
          <li><span>02</span><div><strong>Identity</strong><small>Authentication and tenants</small></div></li>
          <li><span>03</span><div><strong>Operations</strong><small>Patients and appointments</small></div></li>
          <li><span>04</span><div><strong>Clinical ERP</strong><small>Billing, EMR, lab and pharmacy</small></div></li>
        </ol>
      </section>
    </main>
  )
}

export default App
