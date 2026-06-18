import React from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

// Configure QueryClient with safe defaults for transactional operations
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1, // Only retry failed requests once to avoid rate limiting
      refetchOnWindowFocus: false, // Prevent background refetching when switching tabs
      staleTime: 1000 * 30, // 30 seconds stale threshold
    },
  },
});

interface QueryProviderProps {
  children: React.ReactNode;
}

export function QueryProvider({ children }: QueryProviderProps) {
  return (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
}