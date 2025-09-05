<div class="payment-check-container" x-data="paymentChecker()">
    <button 
        @click="checkPayment()"
        :disabled="loading"
        :class="['btn', loading ? 'btn-secondary' : 'btn-primary']"
        class="payment-check-btn"
    >
        <span x-show="!loading">🔍 Check Payment</span>
        <span x-show="loading">⏳ Checking...</span>
    </button>

    <div x-show="result" class="mt-2">
        <div 
            :class="result?.success ? 'alert-success' : 'alert-danger'"
            class="alert"
            x-text="result?.message"
        ></div>
    </div>
</div>

<script>
function paymentChecker() {
    return {
        loading: false,
        result: null,

        async checkPayment() {
            this.loading = true;
            this.result = null;

            try {
                const response = await fetch(`/admin/check-payment?order_number={{ $order->order_number }}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                this.result = await response.json();

                if (this.result.success) {
                    // Refresh page after 2 seconds to show updated status
                    setTimeout(() => window.location.reload(), 2000);
                }

            } catch (error) {
                this.result = {
                    success: false,
                    message: 'Network error: ' + error.message
                };
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

<style>
.payment-check-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    cursor: not-allowed;
}

.alert {
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style> 