import { useState } from 'react';
import { useAdminSubscriptions, useAdminCharges, useRefundMutation } from '../hooks/useAdminData';
import { Loader } from 'lucide-react';

export function AdminDashboard() {
  const [activeTab, setActiveTab] = useState<'subscriptions' | 'charges' | 'telemetry'>('subscriptions');
  const [subFilter, setSubFilter] = useState('all');
  const [chargeFilter, setChargeFilter] = useState('all');

  const { data: subscriptions, isLoading: subsLoading, error: subsError } = useAdminSubscriptions(subFilter);
  const { data: charges, isLoading: chargesLoading, error: chargesError } = useAdminCharges(chargeFilter);
  const refundMutation = useRefundMutation();

  const handleRefund = (chargeId: string) => {
    if (confirm('Are you sure you want to trigger an M-Pesa reversal for this transaction?')) {
      refundMutation.mutate(chargeId);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Platform Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">SubPay Operator Console</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Audit active accounts, handle payment overrides, and monitor system performance.</p>
        </div>
      </div>

      {/* Accessible Tab Interface Control Bar (WAI-ARIA compliance) */}
      <div 
        role="tablist" 
        aria-label="Admin console navigation" 
        className="flex border-b border-gray-200 dark:border-gray-800 mb-6 bg-gray-50 dark:bg-gray-800/40 p-1.5 rounded-lg max-w-md"
      >
        <button
          role="tab"
          id="tab-subscriptions"
          aria-selected={activeTab === 'subscriptions'}
          aria-controls="panel-subscriptions"
          onClick={() => setActiveTab('subscriptions')}
          className={`flex-1 py-2 px-3 text-sm font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 ${
            activeTab === 'subscriptions'
              ? 'bg-white dark:bg-gray-950 text-green-700 dark:text-green-400 shadow-sm'
              : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
          }`}
        >
          Subscriptions
        </button>
        <button
          role="tab"
          id="tab-charges"
          aria-selected={activeTab === 'charges'}
          aria-controls="panel-charges"
          onClick={() => setActiveTab('charges')}
          className={`flex-1 py-2 px-3 text-sm font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 ${
            activeTab === 'charges'
              ? 'bg-white dark:bg-gray-950 text-green-700 dark:text-green-400 shadow-sm'
              : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
          }`}
        >
          Charges
        </button>
        <button
          role="tab"
          id="tab-telemetry"
          aria-selected={activeTab === 'telemetry'}
          aria-controls="panel-telemetry"
          onClick={() => setActiveTab('telemetry')}
          className={`flex-1 py-2 px-3 text-sm font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 ${
            activeTab === 'telemetry'
              ? 'bg-white dark:bg-gray-950 text-green-700 dark:text-green-400 shadow-sm'
              : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
          }`}
        >
          Telemetry
        </button>
      </div>

      {/* Tab Panels */}

      {/* Panel 1: Subscriptions View */}
      <div
        role="tabpanel"
        id="panel-subscriptions"
        aria-labelledby="tab-subscriptions"
        hidden={activeTab !== 'subscriptions'}
        className="focus:outline-none"
      >
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold text-gray-950 dark:text-white">Active Subscriber Roll</h2>
          <select 
            aria-label="Filter subscriptions by status"
            value={subFilter}
            onChange={(e) => setSubFilter(e.target.value)}
            className="px-3 py-1.5 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-800 rounded-lg focus:ring-2 focus:ring-green-500"
          >
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="trialing">Trialing</option>
            <option value="past_due">Past Due</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        {subsLoading ? (
          <div className="flex justify-center py-12"><Loader className="animate-spin text-green-600 h-8 w-8" /></div>
        ) : subsError ? (
          <div className="p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">Failed to retrieve subscriptions list.</div>
        ) : (
          <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 text-xs text-gray-500 uppercase">
                  <th className="px-6 py-3 font-semibold">User</th>
                  <th className="px-6 py-3 font-semibold">Plan</th>
                  <th className="px-6 py-3 font-semibold">Status</th>
                  <th className="px-6 py-3 font-semibold">Next Bill Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                {subscriptions?.map((sub) => (
                  <tr key={sub.id}>
                    <td className="px-6 py-4">
                      <div className="font-semibold text-gray-950 dark:text-white">User {sub.id.substring(0, 8)}</div>
                    </td>
                    <td className="px-6 py-4">{sub.plan.name}</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${
                        sub.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-400' : 'bg-red-100 text-red-800'
                      }`}>
                        {sub.status}
                      </span>
                    </td>
                    <td className="px-6 py-4">{new Date(sub.next_billing_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Panel 2: Charges View */}
      <div
        role="tabpanel"
        id="panel-charges"
        aria-labelledby="tab-charges"
        hidden={activeTab !== 'charges'}
        className="focus:outline-none"
      >
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold text-gray-950 dark:text-white">Recent Transactions Log</h2>
          <select 
            aria-label="Filter charges by status"
            value={chargeFilter}
            onChange={(e) => setChargeFilter(e.target.value)}
            className="px-3 py-1.5 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-800 rounded-lg focus:ring-2 focus:ring-green-500"
          >
            <option value="all">All Transactions</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
          </select>
        </div>

        {chargesLoading ? (
          <div className="flex justify-center py-12"><Loader className="animate-spin text-green-600 h-8 w-8" /></div>
        ) : chargesError ? (
          <div className="p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg">Failed to retrieve transaction logs.</div>
        ) : (
          <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 text-xs text-gray-500 uppercase">
                  <th className="px-6 py-3 font-semibold">Date</th>
                  <th className="px-6 py-3 font-semibold">Amount</th>
                  <th className="px-6 py-3 font-semibold">M-Pesa Receipt</th>
                  <th className="px-6 py-3 font-semibold">Status</th>
                  <th className="px-6 py-3 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                {charges?.map((charge) => (
                  <tr key={charge.id}>
                    <td className="px-6 py-4">{new Date(charge.created_at).toLocaleDateString()}</td>
                    <td className="px-6 py-4 font-semibold text-gray-950 dark:text-white">KES {(charge.amount / 100).toFixed(2)}</td>
                    <td className="px-6 py-4 font-mono text-xs">{charge.mpesa_receipt || '—'}</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${
                        charge.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                      }`}>
                        {charge.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      {charge.status === 'completed' && (
                        <button
                          onClick={() => handleRefund(charge.id)}
                          disabled={refundMutation.isPending}
                          className="px-3 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/40 text-red-600 dark:text-red-400 font-medium text-xs rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                          aria-label={`Initiate refund for transaction ${charge.mpesa_receipt || charge.id}`}
                        >
                          {refundMutation.isPending ? 'Processing...' : 'Reverse Payment'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Panel 3: Grafana Live Telemetry View */}
      <div
        role="tabpanel"
        id="panel-telemetry"
        aria-labelledby="tab-telemetry"
        hidden={activeTab !== 'telemetry'}
        className="focus:outline-none"
      >
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold text-gray-950 dark:text-white">Live System Performance</h2>
        </div>

        {/* Accessible Iframe wrapper for Grafana Metrics Dashboard */}
        <div className="bg-gray-100 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-inner aspect-[16/10] w-full">
          <iframe
            src="http://localhost:3000/d/subpay-metrics/subpay-operational-metrics?orgId=1&kiosk=tv" // kiosk mode hides side navigation bars
            title="SubPay Real-time Prometheus and Grafana Operational Telemetry" // Accessible title for WCAG 2.1 Compliance
            className="w-full h-full border-0"
            allow="fullscreen"
          />
        </div>
      </div>
    </div>
  );
}