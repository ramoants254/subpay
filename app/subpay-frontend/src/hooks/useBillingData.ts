import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';

export interface Plan {
  id: string;
  name: string;
  amount: number; // stored in cents
  billing_cycle: 'daily' | 'weekly' | 'monthly';
  trial_days: number;
  grace_period_days: number;
  is_active: boolean;
}

export interface Charge {
  id: string;
  amount: number;
  status: 'pending' | 'completed' | 'failed' | 'refunded';
  checkout_request_id: string;
  mpesa_receipt: string | null;
  failure_reason?: string | null;
  created_at: string;
}

export interface Subscription {
  id: string;
  status: 'trialing' | 'active' | 'past_due' | 'paused' | 'cancelled';
  trial_ends_at: string | null;
  current_period_start: string;
  current_period_end: string;
  next_billing_at: string;
  plan: Plan;
  charges: Charge[];
}

/**
 * Fetch available billing plans
 */
export function usePlans() {
  return useQuery<Plan[]>({
    queryKey: ['plans'],
    queryFn: async () => {
      const { data } = await apiClient.get('/api/plans');
      return data;
    },
  });
}

/**
 * Fetch current user's active subscriptions and history
 */
export function useMySubscriptions() {
  return useQuery<Subscription[]>({
    queryKey: ['my-subscriptions'],
    queryFn: async () => {
      const { data } = await apiClient.get('/api/subscriptions');
      return data;
    },
  });
}