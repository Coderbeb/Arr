const data = {
    tradeAmounts: [],
    selectedAmountId: '',
    activeTab: 'buy',
    copied: false,
    inQueue: false,
    queuePosition: 0,
    upiId: 'BLADE',
    upiApp: 'BLADE',
    activeTrade: null,
    openOrder: null,
    deepLinks: null,
    dispute: null,
    utrNumber: '',
    screenshotFile: null,
    rejectionFile: null,
    loading: false,
    message: '',
    error: '',

    async init() {
        await this.loadAmounts();
        await this.loadActiveState();
        
        if (window.Echo) {
            window.Echo.private(`user.BLADE`)
                .listen('.trade:update', (e) => {
                    this.inQueue = false;
                    this.loadActiveState();
                });
        }
    },

    async loadAmounts() {
        const res = await fetch('/api/trade/amounts');
        this.tradeAmounts = await res.json();
        if (this.tradeAmounts.length > 0) this.selectedAmountId = this.tradeAmounts[0].id;
    },

    async loadActiveState() {
        try {
            const res = await fetch('/api/trade/my-active');
            const data = await res.json();
            this.activeTrade = data.trade;
            this.openOrder = data.openOrder;
            this.deepLinks = data.deepLinks;
            this.dispute = data.dispute;
        } catch (e) {}
    },

    async handleSellOrder() {
        this.error = ''; this.message = ''; this.loading = true;
        try {
            const res = await fetch('/api/trade/sell', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': 'BLADE'
                },
                body: JSON.stringify({
                    amount_id: this.selectedAmountId,
                    upi_id: this.upiId,
                    upi_app: this.upiApp
                })
            });
            const data = await res.json();
            if (!res.ok) this.error = data.error || 'Failed to post order';
            else {
                this.message = 'Sell order created! Waiting for buyer match.';
                await this.loadActiveState();
            }
        } finally {
            this.loading = false;
        }
    },

    async handleJoinQueue() {
        this.error = ''; this.message = ''; this.loading = true;
        try {
            const res = await fetch('/api/trade/buy/queue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': 'BLADE'
                },
                body: JSON.stringify({ amount_id: this.selectedAmountId })
            });
            const data = await res.json();
            if (!res.ok) {
                this.error = data.error;
            } else {
                this.message = data.message;
                if (data.position > 0) {
                    this.inQueue = true;
                    this.queuePosition = data.position;
                } else {
                    await this.loadActiveState();
                }
            }
        } finally {
            this.loading = false;
        }
    },

    async handleCancelQueue() {
        this.loading = true;
        try {
            const res = await fetch('/api/trade/cancel-queue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': 'BLADE'
                },
                body: JSON.stringify({ amount_id: this.selectedAmountId })
            });
            if (res.ok) {
                this.inQueue = false;
                this.message = 'Left the queue.';
            }
        } finally {
            this.loading = false;
        }
    },

    async submitPayment() {
        if (!this.utrNumber || !this.screenshotFile) {
            this.error = 'Please enter UTR number and upload screenshot.';
            return;
        }
        this.error = ''; this.loading = true;
        const formData = new FormData();
        formData.append('utr_number', this.utrNumber);
        formData.append('screenshot', this.screenshotFile);

        try {
            const res = await fetch(`/api/trade/pay/${this.activeTrade.id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': 'BLADE' },
                body: formData
            });
            const data = await res.json();
            if (!res.ok) this.error = data.error;
            else {
                this.message = data.message;
                await this.loadActiveState();
            }
        } finally {
            this.loading = false;
        }
    },

    async confirmReceipt() {
        this.loading = true;
        try {
            const res = await fetch(`/api/trade/confirm/${this.activeTrade.id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': 'BLADE' }
            });
            const data = await res.json();
            if (!res.ok) this.error = data.error;
            else {
                this.message = 'Trade confirmed and coins released!';
                await this.loadActiveState();
            }
        } finally {
            this.loading = false;
        }
    },

    async handleBuyerCancel() {
        if (!confirm('Are you sure you want to cancel? If you cancel 2 times consecutively, you will be blocked from buying for 15 minutes.')) return;
        this.loading = true;
        try {
            const res = await fetch(`/api/trade/cancel/${this.activeTrade.id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': 'BLADE' }
            });
            const text = await res.text();
            try {
                const data = JSON.parse(text);
                if (!res.ok) this.error = data.error || data.message;
                else {
                    this.message = 'Trade cancelled.';
                    await this.loadActiveState();
                }
            } catch (e) {
                this.error = "Server Error: " + text.substring(0, 50);
            }
        } catch (e) {
            this.error = "Network Error.";
        } finally {
            this.loading = false;
        }
    },

    async handleSellerCancel(orderId) {
        if (!confirm('Are you sure you want to cancel this sell order?')) return;
        this.loading = true;
        try {
            const res = await fetch(`/api/trade/seller-cancel/${orderId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': 'BLADE' }
            });
            const text = await res.text();
            try {
                const data = JSON.parse(text);
                if (!res.ok) this.error = data.error || data.message;
                else {
                    this.message = 'Cancel request processed.';
                    await this.loadActiveState();
                }
            } catch (e) {
                this.error = "Server Error: " + text.substring(0, 50);
            }
        } catch(e) {
            this.error = "Network Error.";
        } finally {
            this.loading = false;
        }
    }
};

