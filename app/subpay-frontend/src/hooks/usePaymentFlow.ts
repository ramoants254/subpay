import { useMutation, useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import type { Charge } from './useBillingData';

interface SubscribePayload {
  plan_id: string;
  phone: string;
}

interface SubscribeResponse {
  subscription_id: string;
  charge: Charge;
}

/**
 * Initiate a subscription, triggering the M-Pesa STK Push
 */
export function useSubscribeMutation() {
  return useMutation<SubscribeResponse, Error, SubscribePayload>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post('/api/subscriptions', payload);
      return data;
    },
  });
}

/**
 * Polls the specific charge status until it is no longer pending
 */
export function usePaymentPolling(chargeId: string | null, isEnabled: boolean) {
  return useQuery<Charge>({
    queryKey: ['charge', chargeId],
    queryFn: async () => {
      const { data } = await apiClient.get(`/api/charges/${chargeId}`);
      return data;
    },
    enabled: !!chargeId && isEnabled,
    refetchInterval: (query) => {
      const currentStatus = query.state.data?.status;
      // Continue polling every 3 seconds strictly while status remains pending
      return currentStatus === 'pending' ? 3000 : false;
    },
  });
}