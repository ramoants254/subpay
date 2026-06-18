import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import { AlertCircle, Loader } from 'lucide-react';

interface LoginProps {
  onSuccess: () => void;
}

export function Login({ onSuccess }: LoginProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  const loginMutation = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post('/api/login', { email, password });
      return data;
    },
    onSuccess: (data) => {
      localStorage.setItem('subpay_token', data.token);
      onSuccess();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Authentication failed. Please verify credentials.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!email || !password) {
      setFormError('Both email and password are required fields.');
      return;
    }

    loginMutation.mutate();
  };

  return (
    <div className="w-full rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-[0_30px_90px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:p-10">
      <div className="mb-8 flex items-center justify-between gap-4">
        <div>
          <h1 className="mt-3 text-3xl font-semibold tracking-tight text-white">Login to SubPay</h1>
        </div>
        <div className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200">
          Operator
        </div>
      </div>
      <p className="mb-8 max-w-sm text-sm leading-6 text-slate-300">
        Enter your credentials to manage subscriptions, reconcile charges, and launch payment flows.
      </p>

      <form onSubmit={handleSubmit} noValidate>
        {formError && (
          <div 
            role="alert" 
            className="mb-4 flex items-start gap-2 rounded-2xl border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-200"
          >
            <AlertCircle className="h-5 w-5 shrink-0" aria-hidden="true" />
            <span>{formError}</span>
          </div>
        )}

        <div className="mb-4">
          <label htmlFor="login-email" className="mb-2 block text-sm font-medium text-slate-200">
            Email Address
          </label>
          <input
            type="email"
            id="login-email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={loginMutation.isPending}
            className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-slate-500 outline-none transition focus:border-cyan-400/60 focus:ring-4 focus:ring-cyan-400/10"
            required
            aria-required="true"
            placeholder="name@company.com"
          />
        </div>

        <div className="mb-6">
          <label htmlFor="login-password" className="mb-2 block text-sm font-medium text-slate-200">
            Password
          </label>
          <input
            type="password"
            id="login-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            disabled={loginMutation.isPending}
            className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-slate-500 outline-none transition focus:border-cyan-400/60 focus:ring-4 focus:ring-cyan-400/10"
            required
            aria-required="true"
            placeholder="••••••••"
          />
        </div>

        <button
          type="submit"
          disabled={loginMutation.isPending}
          className="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 via-sky-500 to-emerald-500 px-4 py-3 font-semibold text-white shadow-[0_16px_40px_rgba(34,211,238,0.22)] transition hover:brightness-110 focus:outline-none focus:ring-4 focus:ring-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-75"
        >
          {loginMutation.isPending ? (
            <>
              <Loader className="animate-spin h-5 w-5" aria-hidden="true" />
              Authenticating...
            </>
          ) : (
            'Login'
          )}
        </button>
      </form>
    </div>
  );
}