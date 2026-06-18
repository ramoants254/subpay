import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import type { Subscription, Charge } from './useBillingData';

export interface AdminStats {
  total_active_subscribers: number;
  total_past_due_subscribers: number;
  total_revenue_cents: number;
}

/**
 * Fetch system-wide subscription list with optional filters
 */
export function useAdminSubscriptions(statusFilter?: string) {
  return useQuery<Subscription[]>({
    queryKey: ['admin-subscriptions', statusFilter],
    queryFn: async () => {
      const params = statusFilter && statusFilter !== 'all' ? { status: statusFilter } : {};
      const { data } = await apiClient.get('/api/admin/subscriptions', { params });
      return data;
    },
  });
}

/**
 * Fetch system-wide charges with optional status filters
 */
export function useAdminCharges(statusFilter?: string) {
  return useQuery<Charge[]>({
    queryKey: ['admin-charges', statusFilter],
    queryFn: async () => {
      const params = statusFilter && statusFilter !== 'all' ? { status: statusFilter } : {};
      const { data } = await apiClient.get('/api/admin/charges', { params });
      return data;
    },
  });
}

/**
 * Initiate an M-Pesa reversal (refund) on a successful transaction charge
 */
export function useRefundMutation() {
  const queryClient = useQueryClient();

  return useMutation<void, Error, string>({
    mutationFn: async (chargeId) => {
      await apiClient.post(`/api/admin/charges/${chargeId}/refund`);
    },
    onSuccess: () => {
      // Refresh charges and subscription views upon successful refund dispatch
      queryClient.invalidateQueries({ queryKey: ['admin-charges'] });
      queryClient.invalidateQueries({ queryKey: ['admin-subscriptions'] });
    },
  });
}