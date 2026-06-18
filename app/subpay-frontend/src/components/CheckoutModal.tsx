import { useEffect, useRef, useState } from 'react';
import type { Plan } from '../hooks/useBillingData';
import { useSubscribeMutation, usePaymentPolling } from '../hooks/usePaymentFlow';
import { CheckoutForm } from './CheckoutForm';
import { Loader, CheckCircle2, XCircle, X } from 'lucide-react';

interface CheckoutModalProps {
  plan: Plan;
  onClose: () => void;
  onSuccess: () => void;
}

export function CheckoutModal({ plan, onClose, onSuccess }: CheckoutModalProps) {
  const modalRef = useRef<HTMLDivElement>(null);
  const [chargeId, setChargeId] = useState<string | null>(null);
  const [isPolling, setIsPolling] = useState(false);
  const [paymentStatus, setPaymentStatus] = useState<'pending' | 'completed' | 'failed'>('pending');

  const subscribeMutation = useSubscribeMutation();
  const { data: chargeUpdate } = usePaymentPolling(chargeId, isPolling);

  // Focus-trapping hook for strict WCAG keyboard navigation compliance
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key !== 'Tab') return;

      const modal = modalRef.current;
      if (!modal) return;

      const focusableElements = modal.querySelectorAll<HTMLElement>(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      
      if (focusableElements.length === 0) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (e.shiftKey) { // Shift + Tab (navigating backward)
        if (document.activeElement === firstElement) {
          lastElement.focus();
          e.preventDefault();
        }
      } else { // Tab (navigating forward)
        if (document.activeElement === lastElement) {
          firstElement.focus();
          e.preventDefault();
        }
      }
    };

    // Store current active element to restore focus on close
    const previousFocus = document.activeElement as HTMLElement;
    document.addEventListener('keydown', handleKeyDown);
    
    // Automatically focus the first element inside the modal upon render
    if (modalRef.current) {
      const firstInput = modalRef.current.querySelector('input');
      firstInput?.focus();
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      previousFocus?.focus();
    };
  }, [onClose]);

  // Handle live polling state transitions as the backend updates
  useEffect(() => {
    if (chargeUpdate) {
      if (chargeUpdate.status === 'completed') {
        setIsPolling(false);
        setPaymentStatus('completed');
        setTimeout(() => {
          onSuccess();
          onClose();
        }, 3000); // Display success modal briefly before resolving
      } else if (chargeUpdate.status === 'failed') {
        setIsPolling(false);
        setPaymentStatus('failed');
      }
    }
  }, [chargeUpdate, onSuccess, onClose]);

  const handleCheckoutSubmit = (phone: string) => {
    subscribeMutation.mutate(
      { plan_id: plan.id, phone },
      {
        onSuccess: (data) => {
          setChargeId(data.charge.id);
          setIsPolling(true);
        },
      }
    );
  };

  return (
    <div 
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-950/85 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-title"
      aria-describedby="modal-description"
    >
      <div 
        ref={modalRef}
        className="relative bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800"
      >
        {/* Close Button */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-1.5 rounded-lg text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors focus:ring-2 focus:ring-green-500"
          aria-label="Close dialog"
        >
          <X className="h-5 w-5" />
        </button>

        {/* State 1: Input Form */}
        {!isPolling && paymentStatus === 'pending' && (
          <div id="modal-description">
            <h1 id="modal-title" className="sr-only">Checkout Form</h1>
            <CheckoutForm
              planName={plan.name}
              amountKsh={plan.amount / 100}
              onSubmit={handleCheckoutSubmit}
              isLoading={subscribeMutation.isPending}
            />
          </div>
        )}

        {/* State 2: Polling for M-Pesa PIN Entry */}
        {isPolling && (
          <div 
            className="flex flex-col items-center justify-center text-center py-8"
            aria-live="polite" // Screen reader announces updates dynamically
            id="modal-description"
          >
            <Loader className="animate-spin h-12 w-12 text-green-600 mb-6" />
            <h2 id="modal-title" className="text-xl font-bold text-gray-900 dark:text-white mb-2">
              Waiting for PIN Entry...
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 max-w-xs">
              An M-Pesa STK Push has been sent to your phone. Please enter your PIN to authorize the payment.
            </p>
          </div>
        )}

        {/* State 3: Payment Completed Success View */}
        {paymentStatus === 'completed' && (
          <div 
            className="flex flex-col items-center justify-center text-center py-8"
            aria-live="assertive"
            id="modal-description"
          >
            <CheckCircle2 className="h-16 w-16 text-green-500 mb-6" />
            <h2 id="modal-title" className="text-xl font-bold text-gray-900 dark:text-white mb-2">
              Payment Complete!
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 max-w-xs">
              Thank you. Your subscription has been successfully activated and your dashboard is updating.
            </p>
          </div>
        )}

        {/* State 4: Payment Failed View */}
        {paymentStatus === 'failed' && (
          <div 
            className="flex flex-col items-center justify-center text-center py-8"
            aria-live="assertive"
            id="modal-description"
          >
            <XCircle className="h-16 w-16 text-red-500 mb-6" />
            <h2 id="modal-title" className="text-xl font-bold text-gray-900 dark:text-white mb-2">
              Payment Failed
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 max-w-xs mb-6">
              {chargeUpdate?.failure_reason || 'The transaction was cancelled or timed out.'}
            </p>
            <button
              onClick={() => {
                setChargeId(null);
                setPaymentStatus('pending');
              }}
              className="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-semibold rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
            >
              Try Again
            </button>
          </div>
        )}
      </div>
    </div>
  );
}