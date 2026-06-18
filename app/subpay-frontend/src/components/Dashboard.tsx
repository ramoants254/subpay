import { useMySubscriptions } from '../hooks/useBillingData';
import { Loader, CheckCircle, Calendar, Clock, AlertCircle } from 'lucide-react';

export function Dashboard() {
  const { data: subscriptions, isLoading, error } = useMySubscriptions();

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-16" role="status" aria-live="polite">
        <Loader className="mb-2 h-8 w-8 animate-spin text-cyan-300" />
        <span className="text-sm text-slate-400">Loading your subscription history...</span>
      </div>
    );
  }

  if (error || !subscriptions) {
    return (
      <div role="alert" className="mx-auto flex max-w-md gap-3 rounded-2xl border border-red-400/20 bg-red-500/10 p-4 text-red-100">
        <AlertCircle className="h-6 w-6 shrink-0" aria-hidden="true" />
        <p className="text-sm font-medium">Unable to load dashboard data.</p>
      </div>
    );
  }

  // If the user has no active subscription records, prompt the catalog selection
  if (subscriptions.length === 0) {
    return (
      <div className="mx-auto max-w-md rounded-[2rem] border border-white/10 bg-white/5 px-6 py-10 text-center backdrop-blur-xl">
        <h3 className="mb-2 text-lg font-semibold text-white">No Active Subscription</h3>
        <p className="text-sm text-slate-400">You are currently not subscribed to any plan.</p>
      </div>
    );
  }

  // Focus on the primary active subscription record
  const sub = subscriptions[0];

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      {/* Active Subscription Summary Card */}
      <div className="mb-8 rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-[0_18px_50px_rgba(0,0,0,0.25)] backdrop-blur-xl">
        <div className="mb-5 flex flex-col items-start justify-between gap-4 border-b border-white/10 pb-5 sm:flex-row sm:items-center">
          <div>
            <span className="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-200/80">Current Plan</span>
            <h2 className="mt-1 text-xl font-semibold text-white">{sub.plan.name}</h2>
          </div>
          <div className="flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-sm font-medium text-emerald-200">
            <CheckCircle className="h-4 w-4" />
            <span className="capitalize">{sub.status}</span>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div className="flex items-center gap-3">
            <Calendar className="h-5 w-5 text-slate-400" />
            <div>
              <p className="text-xs text-slate-400">Current Period End</p>
              <p className="text-sm font-medium text-white">
                {new Date(sub.current_period_end).toLocaleDateString()}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <Clock className="h-5 w-5 text-slate-400" />
            <div>
              <p className="text-xs text-slate-400">Next Scheduled Charge</p>
              <p className="text-sm font-medium text-white">
                {new Date(sub.next_billing_at).toLocaleDateString()}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Subscription Billing Log Tabular Block */}
      <div className="overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 shadow-[0_18px_50px_rgba(0,0,0,0.25)] backdrop-blur-xl">
        <div className="border-b border-white/10 px-6 py-4">
          <h3 className="font-semibold text-white">Billing & Payment History</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-white/10 bg-white/[0.03] text-xs uppercase tracking-[0.18em] text-slate-400">
                <th className="px-6 py-3 font-semibold">Date</th>
                <th className="px-6 py-3 font-semibold">Amount</th>
                <th className="px-6 py-3 font-semibold">Status</th>
                <th className="px-6 py-3 font-semibold">Receipt</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5 text-sm text-slate-300">
              {sub.charges.map((charge) => (
                <tr key={charge.id}>
                  <td className="px-6 py-4">{new Date(charge.created_at).toLocaleDateString()}</td>
                  <td className="px-6 py-4 font-medium text-white">
                    KES {(charge.amount / 100).toFixed(2)}
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${
                      charge.status === 'completed' 
                        ? 'border border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
                        : charge.status === 'pending'
                        ? 'border border-cyan-400/20 bg-cyan-400/10 text-cyan-200'
                        : 'border border-red-400/20 bg-red-500/10 text-red-200'
                    }`}>
                      {charge.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 font-mono text-xs text-slate-400">
                    {charge.mpesa_receipt || '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}