import { useState } from 'react';
import { Phone, AlertCircle, Loader } from 'lucide-react';

interface CheckoutFormProps {
  planName: string;
  amountKsh: number;
  onSubmit: (phone: string) => void;
  isLoading: boolean;
}

export function CheckoutForm({ planName, amountKsh, onSubmit, isLoading }: CheckoutFormProps) {
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    // Validate phone structure (Safaricom formats) before dispatching
    const cleaned = phone.replace(/[^\d+]/g, '');
    const phonePattern = /^(?:254|\+254|0)?([71]\d{8})$/;

    if (!phonePattern.test(cleaned)) {
      setError('Please enter a valid Safaricom number (e.g. 0712345678 or 254712345678)');
      return;
    }

    onSubmit(cleaned);
  };

  return (
    <form 
      onSubmit={handleSubmit} 
      className="max-w-md w-full p-6 bg-white dark:bg-gray-900 rounded-xl shadow-md border border-gray-100 dark:border-gray-800"
      noValidate
    >
      <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-1">
        Subscribe to {planName}
      </h2>
      <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Amount due: <strong className="text-gray-900 dark:text-white">KES {amountKsh.toFixed(2)}</strong>
      </p>

      {/* Interactive Form Input Field with Accessibility Hooks */}
      <div className="mb-5">
        <label 
          htmlFor="phone-input" 
          className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
        >
          M-Pesa Phone Number
        </label>
        
        <div className="relative rounded-md shadow-sm">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <Phone className="h-5 w-5 text-gray-400" aria-hidden="true" />
          </div>
          
          <input
            type="tel"
            id="phone-input"
            name="phone"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            disabled={isLoading}
            required
            aria-required="true"
            aria-invalid={error ? 'true' : 'false'}
            aria-describedby={error ? 'phone-error' : undefined}
            placeholder="e.g. 0712345678"
            className={`block w-full pl-10 pr-3 py-2.5 sm:text-sm rounded-lg border focus:outline-none focus:ring-2 ${
              error 
                ? 'border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500' 
                : 'border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-green-500 focus:border-green-500'
            }`}
          />
        </div>

        {/* Dynamic, Screen-Reader Accessible Error Block */}
        {error && (
          <p 
            id="phone-error" 
            role="alert" 
            className="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1.5"
          >
            <AlertCircle className="h-4 w-4" aria-hidden="true" />
            {error}
          </p>
        )}
      </div>

      <button
        type="submit"
        disabled={isLoading}
        className="w-full py-3 px-4 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-75 flex justify-center items-center gap-2"
      >
        {isLoading ? (
          <>
            <Loader className="animate-spin h-5 w-5" aria-hidden="true" />
            Sending Prompt...
          </>
        ) : (
          'Pay with M-Pesa'
        )}
      </button>
    </form>
  );
}