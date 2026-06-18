import { usePlans } from '../hooks/useBillingData';
import type { Plan } from '../hooks/useBillingData';
import { Loader, AlertTriangle } from 'lucide-react';

interface PlanCatalogProps {
  onSelectPlan: (plan: Plan) => void;
}

export function PlanCatalog({ onSelectPlan }: PlanCatalogProps) {
  const { data: plans, isLoading, error } = usePlans();

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-12" role="status" aria-live="polite">
        <Loader className="mb-2 h-8 w-8 animate-spin text-cyan-300" />
        <span className="text-sm text-slate-400">Fetching available billing plans...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div 
        role="alert" 
        className="mx-auto flex max-w-xl gap-3 rounded-2xl border border-yellow-400/20 bg-yellow-500/10 p-4 text-yellow-100"
      >
        <AlertTriangle className="h-6 w-6 shrink-0" aria-hidden="true" />
        <div>
          <h3 className="font-semibold text-sm">Failed to load plans</h3>
          <p className="text-xs mt-1">Please confirm your connection and try refreshing the browser.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <div className="mb-8 text-center">
        <h2 className="text-3xl font-semibold tracking-tight text-white">Select a Subscription Plan</h2>
        <p className="mt-3 text-sm text-slate-400">All plans are charged securely via automated M-Pesa STK Push prompts.</p>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        {plans?.map((plan) => (
          <div 
            key={plan.id}
            className="flex flex-col rounded-[1.75rem] border border-white/10 bg-white/5 p-6 shadow-[0_18px_50px_rgba(0,0,0,0.25)] backdrop-blur-xl transition hover:border-cyan-400/30 hover:bg-white/[0.07]"
          >
            <h3 className="mb-1 text-lg font-semibold text-white">{plan.name}</h3>
            <span className="mb-4 self-start rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-200">
              {plan.billing_cycle}
            </span>

            <div className="mb-6">
              <span className="text-3xl font-semibold text-white">
                KES {(plan.amount / 100).toFixed(2)}
              </span>
              <span className="text-sm text-slate-400"> / {plan.billing_cycle}</span>
            </div>

            {/* Accessible Button: Ensure a clear ring outline when tab-focused */}
            <button
              onClick={() => onSelectPlan(plan)}
              className="mt-auto w-full rounded-2xl bg-gradient-to-r from-cyan-500 to-emerald-500 py-3 text-sm font-semibold text-white shadow-[0_16px_34px_rgba(34,211,238,0.18)] transition hover:brightness-110 focus:outline-none focus:ring-4 focus:ring-cyan-400/20"
              aria-label={`Subscribe to ${plan.name} for KES ${(plan.amount / 100).toFixed(2)} per ${plan.billing_cycle}`}
            >
              Get Started
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}