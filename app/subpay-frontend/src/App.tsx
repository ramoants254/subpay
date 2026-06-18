import { useState } from 'react';
import { QueryProvider } from './providers/QueryProvider';
import { Login } from './components/Login';
import { PlanCatalog } from './components/PlanCatalog';
import { Dashboard } from './components/Dashboard';
import { CheckoutModal } from './components/CheckoutModal';
import type { Plan } from './hooks/useBillingData';

function AmbientBackdrop() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden">
      <div className="absolute left-1/2 top-[-8rem] h-80 w-80 -translate-x-1/2 rounded-full bg-fuchsia-500/20 blur-3xl" />
      <div className="absolute right-[-6rem] top-20 h-72 w-72 rounded-full bg-cyan-400/15 blur-3xl" />
      <div className="absolute bottom-[-8rem] left-[-4rem] h-80 w-80 rounded-full bg-emerald-400/10 blur-3xl" />
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.04),transparent_55%)]" />
    </div>
  );
}

function MainApp() {
  // Determine authentication status based on the presence of our Sanctum token
  const [isAuthenticated, setIsAuthenticated] = useState(!!localStorage.getItem('subpay_token'));
  const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null);

  const handleLogout = () => {
    localStorage.removeItem('subpay_token');
    setIsAuthenticated(false);
  };

  // State 1: If not logged in, render the login form
  if (!isAuthenticated) {
    return (
      <div className="relative min-h-screen overflow-hidden bg-[#09090f] text-slate-100">
        <AmbientBackdrop />
        <div className="relative mx-auto grid min-h-screen max-w-7xl items-center gap-12 px-6 py-10 lg:grid-cols-[1.05fr_0.95fr]">
          <section className="max-w-2xl space-y-8">
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium uppercase tracking-[0.24em] text-cyan-200/90 backdrop-blur-xl">
              <span className="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_18px_rgba(52,211,153,0.8)]" />
              SubPay operator console
            </div>
            <div className="space-y-5">
              <h1 className="max-w-xl text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                Payments, subscriptions, and recovery flows in one control plane.
              </h1>
              <p className="max-w-xl text-lg leading-8 text-slate-300 text-balance">
                Manage plans, monitor charges, and handle M-Pesa payment states from a single workspace built for fast operator workflows.
              </p>
            </div>
            <div className="grid max-w-xl grid-cols-3 gap-3">
              {[
                ['Realtime', 'charge polling'],
                ['Glass UI', 'dark operator shell'],
                ['Secure', 'Sanctum auth'],
              ].map(([title, subtitle]) => (
                <div key={title} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-xl">
                  <div className="text-sm font-semibold text-white">{title}</div>
                  <div className="mt-1 text-xs text-slate-400">{subtitle}</div>
                </div>
              ))}
            </div>
          </section>

          <div className="relative mx-auto w-full max-w-md">
            <Login onSuccess={() => setIsAuthenticated(true)} />
          </div>
        </div>
      </div>
    );
  }

  // State 2: Authenticated Subscriber Workspace
  return (
    <div className="relative min-h-screen overflow-hidden bg-[#09090f] text-slate-100">
      <AmbientBackdrop />

      <nav className="sticky top-0 z-20 border-b border-white/10 bg-[#09090f]/75 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <div>
            <div className="text-lg font-semibold tracking-tight text-white">SubPay</div>
            <div className="text-xs text-slate-400">Operator workspace</div>
          </div>
          <button
            onClick={handleLogout}
            className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 backdrop-blur-xl transition hover:border-white/20 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-400/70"
          >
            Log Out
          </button>
        </div>
      </nav>

      <main className="relative mx-auto flex max-w-7xl flex-col gap-10 px-6 py-10">
        <Dashboard />

        <section className="rounded-[2rem] border border-white/10 bg-white/5 p-2 shadow-[0_40px_120px_rgba(0,0,0,0.35)] backdrop-blur-xl">
          <PlanCatalog onSelectPlan={(plan) => setSelectedPlan(plan)} />
        </section>
      </main>

      {/* Render checkout modal overlay if a plan is actively selected */}
      {selectedPlan && (
        <CheckoutModal
          plan={selectedPlan}
          onClose={() => setSelectedPlan(null)}
          onSuccess={() => {
            // Reload the page on successful payment to update the subscription status
            window.location.reload();
          }}
        />
      )}
    </div>
  );
}

export default function App() {
  return (
    <QueryProvider>
      <MainApp />
    </QueryProvider>
  );
}