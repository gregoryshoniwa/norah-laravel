<template>
    <div class="payouts-page">
        <header class="page-header">
            <div>
                <h1>Payouts</h1>
                <p>Your expected payout, history, and queries with the gateway.</p>
            </div>
            <button class="btn-primary" @click="openNewQuery">
                <i class="ri-question-answer-line"></i>
                New query
            </button>
        </header>

        <!-- Expected payout cards -->
        <section class="card-section">
            <h2>Expected payout</h2>
            <div v-if="loadingExpected" class="loading">
                <i class="ri-loader-4-line spin"></i> Loading...
            </div>
            <div v-else-if="!expectedRows.length" class="empty">
                You have no outstanding balance right now.
            </div>
            <div v-else class="expected-grid">
                <div v-for="row in expectedRows" :key="row.currency" class="expected-card">
                    <div class="expected-card-header">
                        <span class="currency-tag">{{ row.currency }}</span>
                        <span class="total">{{ formatAmount(row.total) }}</span>
                    </div>
                    <table class="breakdown">
                        <thead>
                            <tr><th>Method</th><th class="num">Count</th><th class="num">Total</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in row.by_method" :key="m.payment_method">
                                <td>{{ formatMethod(m.payment_method) }}</td>
                                <td class="num">{{ m.count }}</td>
                                <td class="num">{{ formatAmount(m.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- History -->
        <section class="card-section">
            <h2>Payout history</h2>
            <div class="filter-row">
                <select v-model="historyFilters.status" @change="loadHistory">
                    <option value="">All statuses</option>
                    <option>PENDING</option>
                    <option>SENT</option>
                    <option>CONFIRMED</option>
                    <option>DISPUTED</option>
                </select>
                <select v-model="historyFilters.currency" @change="loadHistory">
                    <option value="">All currencies</option>
                    <option>USD</option>
                    <option>ZWL</option>
                </select>
            </div>

            <div v-if="loadingHistory" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <table v-else class="history-table">
                <thead>
                    <tr>
                        <th>#</th><th>Date</th><th>Currency</th><th>Amount</th><th>Period</th><th>Status</th><th>Bank ref</th><th></th>
                    </tr>
                </thead>
                <tbody v-if="history.length">
                    <tr v-for="p in history" :key="p.id">
                        <td>#{{ p.id }}</td>
                        <td>{{ formatDate(p.created_at) }}</td>
                        <td>{{ p.currency }}</td>
                        <td>{{ formatAmount(p.amount) }}</td>
                        <td>{{ p.period_start || '—' }} → {{ p.period_end || '—' }}</td>
                        <td><span :class="['badge', p.status.toLowerCase()]">{{ p.status }}</span></td>
                        <td>{{ p.bank_reference || '—' }}</td>
                        <td>
                            <button class="link-btn" @click="openPayout(p.id)">View</button>
                            <button v-if="p.status === 'SENT'" class="link-btn success" @click="confirmPayout(p.id)">Confirm receipt</button>
                            <button v-if="p.status === 'SENT'" class="link-btn danger" @click="disputePayout(p)">Dispute</button>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr><td colspan="8" class="empty">No payouts yet.</td></tr>
                </tbody>
            </table>
        </section>

        <!-- Queries -->
        <section class="card-section">
            <h2>Queries</h2>
            <div v-if="loadingThreads" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <table v-else class="history-table">
                <thead><tr><th>#</th><th>Subject</th><th>Payout</th><th>Status</th><th>Replies</th><th>Updated</th><th></th></tr></thead>
                <tbody v-if="threads.length">
                    <tr v-for="t in threads" :key="t.id">
                        <td>#{{ t.id }}</td>
                        <td>{{ t.subject }}</td>
                        <td>{{ t.payout_id ? `#${t.payout_id}` : '—' }}</td>
                        <td><span :class="['badge', t.status.toLowerCase()]">{{ t.status }}</span></td>
                        <td>{{ t.replies_count }}</td>
                        <td>{{ formatDate(t.updated_at) }}</td>
                        <td><button class="link-btn" @click="openThread(t.id)">Open</button></td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr><td colspan="7" class="empty">No queries yet.</td></tr>
                </tbody>
            </table>
        </section>

        <!-- Payout detail modal -->
        <div v-if="payoutModal.open" class="modal-overlay" @mousedown.self="closePayout">
            <div class="modal-card wide">
                <header class="modal-header">
                    <h3>Payout #{{ payoutModal.data?.id }}</h3>
                    <button class="close" @click="closePayout">&times;</button>
                </header>
                <div class="modal-body" v-if="payoutModal.data">
                    <div class="kv">
                        <div><label>Amount</label><span>{{ payoutModal.data.currency }} {{ formatAmount(payoutModal.data.amount) }}</span></div>
                        <div><label>Status</label><span :class="['badge', payoutModal.data.status.toLowerCase()]">{{ payoutModal.data.status }}</span></div>
                        <div><label>Bank ref</label><span>{{ payoutModal.data.bank_reference || '—' }}</span></div>
                        <div><label>Sent at</label><span>{{ formatDate(payoutModal.data.sent_at) }}</span></div>
                        <div><label>Confirmed at</label><span>{{ formatDate(payoutModal.data.confirmed_at) }}</span></div>
                    </div>
                    <h4>Transactions included</h4>
                    <table class="history-table">
                        <thead><tr><th>Txn ID</th><th>Date</th><th>Method</th><th>Source</th><th>Amount</th><th>Reference</th></tr></thead>
                        <tbody>
                            <tr v-for="it in payoutModal.data.items" :key="it.id">
                                <td>#{{ it.transaction_id }}</td>
                                <td>{{ formatDate(it.transaction?.created_at) }}</td>
                                <td>{{ formatMethod(it.transaction?.payment_method) }}</td>
                                <td>{{ it.source_type }}</td>
                                <td>{{ formatAmount(it.amount) }}</td>
                                <td>{{ it.transaction?.customer_reference || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer class="modal-footer">
                    <button class="btn-secondary" @click="closePayout">Close</button>
                </footer>
            </div>
        </div>

        <!-- New query modal -->
        <div v-if="queryModal.open" class="modal-overlay" @mousedown.self="closeNewQuery">
            <div class="modal-card">
                <header class="modal-header">
                    <h3>New query</h3>
                    <button class="close" @click="closeNewQuery">&times;</button>
                </header>
                <div class="modal-body">
                    <label>Related payout (optional)</label>
                    <select v-model="queryModal.payout_id">
                        <option :value="null">None</option>
                        <option v-for="p in history" :key="p.id" :value="p.id">#{{ p.id }} — {{ p.currency }} {{ formatAmount(p.amount) }}</option>
                    </select>
                    <label>Subject</label>
                    <input v-model="queryModal.subject" maxlength="120" placeholder="Short summary" />
                    <label>Message</label>
                    <textarea v-model="queryModal.body" rows="5" placeholder="Describe the issue..." maxlength="5000"></textarea>
                </div>
                <footer class="modal-footer">
                    <button class="btn-secondary" @click="closeNewQuery">Cancel</button>
                    <button class="btn-primary" :disabled="!canSendQuery" @click="sendQuery">Send</button>
                </footer>
            </div>
        </div>

        <!-- Thread modal -->
        <div v-if="threadModal.open" class="modal-overlay" @mousedown.self="closeThread">
            <div class="modal-card wide">
                <header class="modal-header">
                    <h3>{{ threadModal.data?.subject }}</h3>
                    <button class="close" @click="closeThread">&times;</button>
                </header>
                <div class="modal-body thread-body" v-if="threadModal.data">
                    <article class="msg" :class="messageClass(threadModal.data)">
                        <div class="msg-meta"><strong>{{ senderName(threadModal.data) }}</strong> <span>{{ threadModal.data.sender_role }}</span> · {{ formatDate(threadModal.data.created_at) }}</div>
                        <p>{{ threadModal.data.body }}</p>
                    </article>
                    <article v-for="r in threadModal.data.replies" :key="r.id" class="msg" :class="messageClass(r)">
                        <div class="msg-meta"><strong>{{ senderName(r) }}</strong> <span>{{ r.sender_role }}</span> · {{ formatDate(r.created_at) }}</div>
                        <p>{{ r.body }}</p>
                    </article>
                </div>
                <footer class="modal-footer column">
                    <textarea v-model="threadModal.reply" rows="3" placeholder="Type a reply..." maxlength="5000"></textarea>
                    <div class="actions">
                        <button class="btn-secondary" @click="closeThread">Close</button>
                        <button class="btn-primary" :disabled="!threadModal.reply.trim()" @click="sendReply">Reply</button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'Payouts',
    data() {
        return {
            loadingExpected: false,
            expectedRows: [],
            loadingHistory: false,
            history: [],
            historyFilters: { status: '', currency: '' },
            loadingThreads: false,
            threads: [],
            payoutModal: { open: false, data: null },
            queryModal: { open: false, subject: '', body: '', payout_id: null },
            threadModal: { open: false, data: null, reply: '' },
        };
    },
    computed: {
        canSendQuery() {
            return this.queryModal.subject.trim().length > 0 && this.queryModal.body.trim().length > 4;
        },
    },
    created() {
        this.bootstrap();
        this.loadExpected();
        this.loadHistory();
        this.loadThreads();
    },
    methods: {
        bootstrap() {
            const token = localStorage.getItem('authToken');
            if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },
        formatAmount(v) {
            const n = Number(v);
            if (isNaN(n)) return '0.00';
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
        },
        formatMethod(m) {
            if (!m) return '—';
            const map = { VISA_MASTER: 'Visa/Master', ZIMSWITCH: 'Zimswitch', ECOCASH: 'EcoCash', OMARI: 'O’mari', INNBUCKS: 'InnBucks' };
            return map[m] || m;
        },
        senderName(m) {
            const s = m.sender;
            if (!s) return 'Unknown';
            return [s.first_name, s.last_name].filter(Boolean).join(' ') || s.email;
        },
        messageClass(m) {
            return m.sender_role === 'SUPER' ? 'from-super' : 'from-self';
        },
        async loadExpected() {
            this.loadingExpected = true;
            try {
                const { data } = await axios.get('/api/v1/payouts/expected');
                this.expectedRows = data?.data?.by_currency || [];
            } catch (e) { /* interceptor handles 401 */ }
            finally { this.loadingExpected = false; }
        },
        async loadHistory() {
            this.loadingHistory = true;
            try {
                const params = {};
                if (this.historyFilters.status) params.status = this.historyFilters.status;
                if (this.historyFilters.currency) params.currency = this.historyFilters.currency;
                const { data } = await axios.get('/api/v1/payouts', { params });
                this.history = data?.data?.data || [];
            } catch (e) {}
            finally { this.loadingHistory = false; }
        },
        async loadThreads() {
            this.loadingThreads = true;
            try {
                const { data } = await axios.get('/api/v1/payouts/messages');
                this.threads = data?.data?.data || [];
            } catch (e) {}
            finally { this.loadingThreads = false; }
        },
        async openPayout(id) {
            try {
                const { data } = await axios.get(`/api/v1/payouts/${id}`);
                this.payoutModal = { open: true, data: data.data };
            } catch (e) {}
        },
        closePayout() { this.payoutModal = { open: false, data: null }; },
        openNewQuery() {
            this.queryModal = { open: true, subject: '', body: '', payout_id: null };
        },
        closeNewQuery() { this.queryModal.open = false; },
        async sendQuery() {
            try {
                await axios.post('/api/v1/payouts/messages', {
                    subject: this.queryModal.subject.trim(),
                    body: this.queryModal.body.trim(),
                    payout_id: this.queryModal.payout_id,
                });
                this.closeNewQuery();
                this.loadThreads();
                this.$swal?.fire('Sent', 'Your query has been submitted.', 'success');
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to send.', 'error');
            }
        },
        async openThread(id) {
            try {
                const { data } = await axios.get(`/api/v1/payouts/messages/${id}`);
                this.threadModal = { open: true, data: data.data, reply: '' };
            } catch (e) {}
        },
        closeThread() { this.threadModal = { open: false, data: null, reply: '' }; },
        async sendReply() {
            try {
                await axios.post(`/api/v1/payouts/messages/${this.threadModal.data.id}/reply`, { body: this.threadModal.reply.trim() });
                this.openThread(this.threadModal.data.id);
                this.loadThreads();
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to reply.', 'error');
            }
        },
        async confirmPayout(id) {
            const ok = await this.$swal?.fire({ title: 'Confirm receipt?', text: 'Confirm you received this bank transfer.', icon: 'question', showCancelButton: true });
            if (!ok?.isConfirmed) return;
            try {
                await axios.post(`/api/v1/payouts/${id}/confirm`);
                this.loadHistory();
                this.$swal?.fire('Done', 'Payout confirmed.', 'success');
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to confirm.', 'error');
            }
        },
        async disputePayout(p) {
            const { value: body } = await this.$swal?.fire({
                title: `Dispute payout #${p.id}`,
                input: 'textarea',
                inputLabel: 'Reason',
                inputPlaceholder: 'Describe what is wrong...',
                showCancelButton: true,
            }) || {};
            if (!body) return;
            try {
                await axios.post(`/api/v1/payouts/${p.id}/dispute`, { body });
                this.loadHistory();
                this.loadThreads();
                this.$swal?.fire('Submitted', 'Dispute lodged with the gateway.', 'success');
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to submit dispute.', 'error');
            }
        },
    },
};
</script>

<style scoped>
.payouts-page { padding: 24px; max-width: 1400px; margin: 0 auto; color: #1e293b; }
.page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
.page-header h1 { margin: 0; font-size: 1.8rem; }
.page-header p { margin: 4px 0 0 0; color: #64748b; }
.btn-primary, .btn-secondary { padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 0.9rem; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #334155; }
.card-section { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.card-section h2 { margin: 0 0 16px 0; font-size: 1.1rem; color: #0f172a; }
.expected-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
.expected-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
.expected-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.currency-tag { background: #eef2ff; color: #4f46e5; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; }
.total { font-size: 1.4rem; font-weight: 700; color: #0f172a; }
.breakdown { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.breakdown th, .breakdown td { padding: 6px 4px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.breakdown .num { text-align: right; }
.filter-row { display: flex; gap: 12px; margin-bottom: 12px; }
.filter-row select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
.history-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.history-table th, .history-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.history-table .empty { text-align: center; color: #94a3b8; padding: 30px 0; }
.badge { padding: 3px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.badge.pending { background: #fef3c7; color: #92400e; }
.badge.sent { background: #dbeafe; color: #1e40af; }
.badge.confirmed { background: #dcfce7; color: #166534; }
.badge.disputed { background: #fee2e2; color: #991b1b; }
.badge.open { background: #dbeafe; color: #1e40af; }
.badge.resolved { background: #dcfce7; color: #166534; }
.link-btn { background: none; border: none; color: #4f46e5; cursor: pointer; margin-right: 8px; font-weight: 600; }
.link-btn.success { color: #16a34a; }
.link-btn.danger { color: #dc2626; }
.loading, .empty { color: #94a3b8; padding: 20px 0; text-align: center; }
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: flex; align-items: center; justify-content: center; z-index: 5000; padding: 16px; }
.modal-card { background: #fff; border-radius: 12px; padding: 0; max-width: 520px; width: 100%; }
.modal-card.wide { max-width: 800px; }
.modal-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; }
.modal-header .close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
.modal-body { padding: 20px; }
.modal-body label { display: block; margin: 10px 0 4px 0; font-weight: 500; font-size: 0.85rem; color: #334155; }
.modal-body input, .modal-body textarea, .modal-body select { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }
.modal-footer.column { flex-direction: column; align-items: stretch; gap: 12px; }
.modal-footer .actions { display: flex; justify-content: flex-end; gap: 8px; }
.kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
.kv div { display: flex; flex-direction: column; }
.kv label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
.kv span { font-weight: 600; }
.thread-body { max-height: 60vh; overflow-y: auto; }
.msg { background: #f8fafc; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
.msg.from-super { background: #eef2ff; }
.msg-meta { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
.msg p { margin: 0; white-space: pre-wrap; }
.spin { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
