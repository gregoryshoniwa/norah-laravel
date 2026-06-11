<template>
    <div class="payouts-page">
        <header class="page-header">
            <div>
                <h1>Payouts</h1>
                <p>Manage payouts to companies and sub-merchants, view P&amp;L, respond to queries.</p>
            </div>
        </header>

        <div class="tabs">
            <button :class="['tab', { active: tab === 'outstanding' }]" @click="tab = 'outstanding'">Outstanding</button>
            <button :class="['tab', { active: tab === 'history' }]" @click="tab = 'history'">History</button>
            <button :class="['tab', { active: tab === 'pl' }]" @click="tab = 'pl'">Profit &amp; Loss</button>
            <button :class="['tab', { active: tab === 'queries' }]" @click="tab = 'queries'">Queries <span v-if="openQueriesCount" class="badge-count">{{ openQueriesCount }}</span></button>
            <button :class="['tab', { active: tab === 'schedules' }]" @click="tab = 'schedules'">Schedules</button>
        </div>

        <!-- OUTSTANDING -->
        <section v-if="tab === 'outstanding'" class="card-section">
            <div class="filter-row">
                <select v-model="outFilters.role" @change="loadOutstanding">
                    <option value="">All recipients</option>
                    <option value="ADMIN">ADMIN (companies)</option>
                    <option value="MERCHANT">MERCHANT (sub-merchants)</option>
                </select>
                <select v-model="outFilters.currency" @change="loadOutstanding">
                    <option value="">All currencies</option>
                    <option>USD</option>
                    <option>ZWL</option>
                </select>
                <select v-model="outFilters.payment_method" @change="loadOutstanding">
                    <option value="">All payment methods</option>
                    <option>VISA_MASTER</option>
                    <option>ZIMSWITCH</option>
                    <option>ECOCASH</option>
                    <option>OMARI</option>
                    <option>INNBUCKS</option>
                </select>
            </div>

            <div v-if="loadingOutstanding" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <table v-else class="data-table">
                <thead><tr><th>Recipient</th><th>Role</th><th>Owed</th><th></th></tr></thead>
                <tbody v-if="outstanding.length">
                    <tr v-for="r in outstanding" :key="r.user_id">
                        <td>
                            <div><strong>{{ r.name }}</strong></div>
                            <div class="muted">{{ r.email }}</div>
                            <div v-if="r.bank_complete" class="bank-ok">
                                <i class="ri-bank-line"></i> {{ r.bank_name }} · {{ maskAccount(r.bank_account_number) }}
                            </div>
                            <div v-else class="bank-missing">
                                <i class="ri-error-warning-line"></i>
                                Bank details missing
                                <button class="link-btn" @click="openBankEditor(r)">Add</button>
                            </div>
                        </td>
                        <td><span :class="['role-badge', r.role.toLowerCase()]">{{ r.role }}</span></td>
                        <td>
                            <div v-for="b in r.breakdown" :key="b.currency" class="bal-line">
                                <strong>{{ b.currency }} {{ formatAmount(b.total) }}</strong>
                                <div class="bal-methods">
                                    <span v-for="m in b.by_method" :key="m.payment_method">
                                        {{ formatMethod(m.payment_method) }}: {{ formatAmount(m.total) }} <em>({{ m.count }})</em>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <button v-for="b in r.breakdown" :key="b.currency" class="btn-primary small"
                                :disabled="!r.bank_complete"
                                :title="r.bank_complete ? '' : 'Add bank details first'"
                                @click="openCreatePayout(r, b)">
                                Create {{ b.currency }} payout
                            </button>
                            <button class="link-btn" @click="openBankEditor(r)">Edit bank</button>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else><tr><td colspan="4" class="empty">No outstanding balances.</td></tr></tbody>
            </table>
        </section>

        <!-- HISTORY -->
        <section v-if="tab === 'history'" class="card-section">
            <div class="filter-row">
                <select v-model="histFilters.status" @change="loadHistory">
                    <option value="">All statuses</option>
                    <option>PENDING</option><option>SENT</option><option>CONFIRMED</option><option>DISPUTED</option>
                </select>
                <select v-model="histFilters.currency" @change="loadHistory">
                    <option value="">All currencies</option><option>USD</option><option>ZWL</option>
                </select>
                <input type="date" v-model="histFilters.start_date" @change="loadHistory" />
                <input type="date" v-model="histFilters.end_date" @change="loadHistory" />
            </div>

            <div v-if="loadingHistory" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <table v-else class="data-table">
                <thead><tr><th>#</th><th>Recipient</th><th>Currency</th><th>Amount</th><th>Period</th><th>Status</th><th>Bank ref</th><th>Created</th><th></th></tr></thead>
                <tbody v-if="history.length">
                    <tr v-for="p in history" :key="p.id">
                        <td>#{{ p.id }}</td>
                        <td>
                            <strong>{{ recipientName(p.recipient) }}</strong>
                            <div class="muted">{{ p.recipient?.email }}</div>
                        </td>
                        <td>{{ p.currency }}</td>
                        <td>{{ formatAmount(p.amount) }}</td>
                        <td>{{ p.period_start || '—' }} → {{ p.period_end || '—' }}</td>
                        <td><span :class="['badge', p.status.toLowerCase()]">{{ p.status }}</span></td>
                        <td>{{ p.bank_reference || '—' }}</td>
                        <td>{{ formatDate(p.created_at) }}</td>
                        <td>
                            <button class="link-btn" @click="openPayout(p.id)">View</button>
                            <button v-if="p.status === 'PENDING'" class="link-btn success" @click="openMarkSent(p)">Mark sent</button>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else><tr><td colspan="9" class="empty">No payouts.</td></tr></tbody>
            </table>
        </section>

        <!-- P&L -->
        <section v-if="tab === 'pl'" class="card-section">
            <div class="filter-row">
                <input type="date" v-model="plFilters.start_date" @change="loadPL" />
                <input type="date" v-model="plFilters.end_date" @change="loadPL" />
                <select v-model="plFilters.currency" @change="loadPL">
                    <option value="">All currencies</option><option>USD</option><option>ZWL</option>
                </select>
                <select v-model="plFilters.payment_method" @change="loadPL">
                    <option value="">All payment methods</option>
                    <option>VISA_MASTER</option><option>ZIMSWITCH</option><option>ECOCASH</option><option>OMARI</option><option>INNBUCKS</option>
                </select>
            </div>

            <div v-if="loadingPL" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <div v-else class="pl-grid">
                <div class="pl-card profit">
                    <div class="pl-label">Gateway profit (SYSTEM_CHARGE)</div>
                    <div v-for="(amt, cur) in pl.gateway_profit.by_currency" :key="cur" class="pl-amount">
                        {{ cur }} {{ formatAmount(amt) }}
                    </div>
                </div>
                <div class="pl-card">
                    <div class="pl-label">Merchant fees → ADMIN accounts</div>
                    <div v-for="(amt, cur) in pl.merchant_fees_to_admin.by_currency" :key="cur" class="pl-amount">
                        {{ cur }} {{ formatAmount(amt) }}
                    </div>
                </div>
                <div class="pl-card">
                    <div class="pl-label">Gross payments processed</div>
                    <div v-for="(amt, cur) in pl.gross_payments_processed.by_currency" :key="cur" class="pl-amount">
                        {{ cur }} {{ formatAmount(amt) }}
                    </div>
                </div>
            </div>

            <h3>Gateway profit by method</h3>
            <table class="data-table">
                <thead><tr><th>Currency</th><th>Method</th><th>Count</th><th>Total</th></tr></thead>
                <tbody>
                    <tr v-for="(r, i) in pl.gateway_profit.by_method" :key="'gp'+i">
                        <td>{{ r.currency }}</td><td>{{ formatMethod(r.payment_method) }}</td><td>{{ r.count }}</td><td>{{ formatAmount(r.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- QUERIES -->
        <section v-if="tab === 'queries'" class="card-section">
            <div class="filter-row">
                <select v-model="qStatus" @change="loadInbox">
                    <option value="OPEN">Open</option>
                    <option value="RESOLVED">Resolved</option>
                </select>
            </div>
            <div v-if="loadingInbox" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <table v-else class="data-table">
                <thead><tr><th>#</th><th>From</th><th>Subject</th><th>Payout</th><th>Status</th><th>Replies</th><th>Updated</th><th></th></tr></thead>
                <tbody v-if="inbox.length">
                    <tr v-for="t in inbox" :key="t.id">
                        <td>#{{ t.id }}</td>
                        <td>
                            <strong>{{ recipientName(t.recipient) }}</strong>
                            <div class="muted">{{ t.recipient?.role }} · {{ t.recipient?.email }}</div>
                        </td>
                        <td>{{ t.subject }}</td>
                        <td>{{ t.payout_id ? `#${t.payout_id}` : '—' }}</td>
                        <td><span :class="['badge', t.status.toLowerCase()]">{{ t.status }}</span></td>
                        <td>{{ t.replies_count }}</td>
                        <td>{{ formatDate(t.updated_at) }}</td>
                        <td><button class="link-btn" @click="openThread(t.id)">Open</button></td>
                    </tr>
                </tbody>
                <tbody v-else><tr><td colspan="8" class="empty">No threads.</td></tr></tbody>
            </table>
        </section>

        <!-- SCHEDULES -->
        <section v-if="tab === 'schedules'" class="card-section">
            <div class="filter-row" style="justify-content: space-between;">
                <div class="muted">Auto-scheduled payouts run via the cron daily check. PENDING payouts are created — admin still marks them sent after the bank transfer.</div>
                <button class="btn-primary" @click="openScheduleEditor()">+ New schedule</button>
            </div>

            <div v-if="loadingSchedules" class="loading"><i class="ri-loader-4-line spin"></i> Loading...</div>
            <table v-else class="data-table">
                <thead><tr>
                    <th>#</th><th>Scope</th><th>Currency</th><th>Cadence</th>
                    <th>Min</th><th>Cutoff</th><th>Active</th><th>Last run</th><th></th>
                </tr></thead>
                <tbody v-if="schedules.length">
                    <tr v-for="s in schedules" :key="s.id">
                        <td>#{{ s.id }}</td>
                        <td>{{ scheduleScopeLabel(s) }}</td>
                        <td>{{ s.currency }}</td>
                        <td>{{ scheduleCadenceLabel(s) }}</td>
                        <td>{{ formatAmount(s.minimum_amount) }}</td>
                        <td>{{ s.cutoff_hours_back }}h</td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" :checked="s.is_active" @change="toggleActive(s, $event.target.checked)" />
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td>
                            <div>{{ formatDate(s.last_run_at) }}</div>
                            <div class="muted" v-if="s.last_run_summary">
                                {{ s.last_run_summary.payouts_created }} created · {{ s.currency }} {{ formatAmount(s.last_run_summary.total_created_amount) }}
                            </div>
                        </td>
                        <td>
                            <button class="link-btn" @click="openScheduleEditor(s)">Edit</button>
                            <button class="link-btn success" @click="runScheduleNow(s)">Run now</button>
                            <button class="link-btn danger" @click="deleteSchedule(s)">Delete</button>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else><tr><td colspan="9" class="empty">No schedules configured. The first one starts auto-payouts.</td></tr></tbody>
            </table>
        </section>

        <!-- Schedule editor modal -->
        <div v-if="scheduleModal.open" class="modal-overlay" @mousedown.self="scheduleModal.open=false">
            <div class="modal-card">
                <header class="modal-header">
                    <h3>{{ scheduleModal.id ? 'Edit schedule' : 'New schedule' }}</h3>
                    <button class="close" @click="scheduleModal.open=false">&times;</button>
                </header>
                <div class="modal-body">
                    <label>Scope</label>
                    <select v-model="scheduleModal.scope" @change="onScopeChange">
                        <option value="all">All recipients (ADMIN + MERCHANT)</option>
                        <option value="role-admin">All ADMIN (companies)</option>
                        <option value="role-merchant">All MERCHANT (sub-merchants)</option>
                        <option value="single">Single recipient</option>
                    </select>

                    <div v-if="scheduleModal.scope === 'single'">
                        <label>Recipient</label>
                        <select v-model="scheduleModal.recipient_user_id">
                            <option :value="null" disabled>Select recipient...</option>
                            <optgroup label="ADMIN">
                                <option v-for="u in allRecipients.filter(x => x.role === 'ADMIN')" :key="u.id" :value="u.id">{{ recipientLabel(u) }}</option>
                            </optgroup>
                            <optgroup label="MERCHANT">
                                <option v-for="u in allRecipients.filter(x => x.role === 'MERCHANT')" :key="u.id" :value="u.id">{{ recipientLabel(u) }}</option>
                            </optgroup>
                        </select>
                    </div>

                    <label>Currency</label>
                    <select v-model="scheduleModal.currency">
                        <option>USD</option><option>ZWL</option>
                    </select>

                    <label>Cadence</label>
                    <select v-model="scheduleModal.cadence">
                        <option>DAILY</option><option>WEEKLY</option><option>MONTHLY</option>
                    </select>

                    <div v-if="scheduleModal.cadence === 'WEEKLY'">
                        <label>Day of week</label>
                        <select v-model="scheduleModal.day_of_week">
                            <option :value="1">Monday</option>
                            <option :value="2">Tuesday</option>
                            <option :value="3">Wednesday</option>
                            <option :value="4">Thursday</option>
                            <option :value="5">Friday</option>
                            <option :value="6">Saturday</option>
                            <option :value="7">Sunday</option>
                        </select>
                    </div>
                    <div v-if="scheduleModal.cadence === 'MONTHLY'">
                        <label>Day of month (1-28)</label>
                        <input type="number" min="1" max="28" v-model.number="scheduleModal.day_of_month" />
                    </div>

                    <label>Minimum amount (skip recipients below this)</label>
                    <input type="number" step="0.01" min="0" v-model.number="scheduleModal.minimum_amount" />

                    <label>Cutoff (hours back — only include transactions older than this, to allow disputes)</label>
                    <input type="number" min="0" max="720" v-model.number="scheduleModal.cutoff_hours_back" />

                    <label>Default notes (added to each created payout)</label>
                    <textarea v-model="scheduleModal.default_notes" rows="2" maxlength="500" placeholder="Optional"></textarea>

                    <label class="inline">
                        <input type="checkbox" v-model="scheduleModal.is_active" /> Active
                    </label>
                </div>
                <footer class="modal-footer">
                    <button class="btn-secondary" @click="scheduleModal.open=false">Cancel</button>
                    <button class="btn-primary" @click="saveSchedule">{{ scheduleModal.id ? 'Save' : 'Create' }}</button>
                </footer>
            </div>
        </div>

        <!-- Create payout modal -->
        <div v-if="createModal.open" class="modal-overlay" @mousedown.self="createModal.open=false">
            <div class="modal-card">
                <header class="modal-header"><h3>Create payout</h3><button class="close" @click="createModal.open=false">&times;</button></header>
                <div class="modal-body">
                    <div class="kv">
                        <div><label>Recipient</label><span>{{ createModal.recipientName }}</span></div>
                        <div><label>Currency</label><span>{{ createModal.currency }}</span></div>
                        <div><label>Estimated total</label><span>{{ formatAmount(createModal.estimatedTotal) }}</span></div>
                    </div>
                    <label>Cutoff date (optional - only include transactions up to this date)</label>
                    <input type="date" v-model="createModal.cutoff_date" />
                    <label>Notes (optional)</label>
                    <textarea v-model="createModal.notes" rows="3"></textarea>
                </div>
                <footer class="modal-footer">
                    <button class="btn-secondary" @click="createModal.open=false">Cancel</button>
                    <button class="btn-primary" @click="createPayout">Create</button>
                </footer>
            </div>
        </div>

        <!-- Mark sent modal -->
        <div v-if="sentModal.open" class="modal-overlay" @mousedown.self="sentModal.open=false">
            <div class="modal-card">
                <header class="modal-header"><h3>Mark payout #{{ sentModal.payout?.id }} as sent</h3><button class="close" @click="sentModal.open=false">&times;</button></header>
                <div class="modal-body">
                    <label>Bank reference</label>
                    <input v-model="sentModal.bank_reference" placeholder="e.g. CBZ-TXN-123456" maxlength="120" />
                    <label>Notes (optional)</label>
                    <textarea v-model="sentModal.notes" rows="3"></textarea>
                </div>
                <footer class="modal-footer">
                    <button class="btn-secondary" @click="sentModal.open=false">Cancel</button>
                    <button class="btn-primary" @click="markSent">Mark sent</button>
                </footer>
            </div>
        </div>

        <!-- Payout detail modal -->
        <div v-if="payoutModal.open" class="modal-overlay" @mousedown.self="payoutModal.open=false">
            <div class="modal-card wide">
                <header class="modal-header"><h3>Payout #{{ payoutModal.data?.id }}</h3><button class="close" @click="payoutModal.open=false">&times;</button></header>
                <div class="modal-body" v-if="payoutModal.data">
                    <div class="kv">
                        <div><label>Recipient</label><span>{{ recipientName(payoutModal.data.recipient) }}</span></div>
                        <div><label>Amount</label><span>{{ payoutModal.data.currency }} {{ formatAmount(payoutModal.data.amount) }}</span></div>
                        <div><label>Status</label><span :class="['badge', payoutModal.data.status.toLowerCase()]">{{ payoutModal.data.status }}</span></div>
                        <div><label>Bank ref</label><span>{{ payoutModal.data.bank_reference || '—' }}</span></div>
                    </div>
                    <h4>Transactions ({{ payoutModal.data.items?.length || 0 }})</h4>
                    <table class="data-table">
                        <thead><tr><th>Txn #</th><th>Date</th><th>Method</th><th>Source</th><th>Amount</th><th>Customer ref</th></tr></thead>
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
                    <button class="btn-secondary" @click="payoutModal.open=false">Close</button>
                </footer>
            </div>
        </div>

        <!-- Bank editor modal (super-admin) -->
        <div v-if="bankModal.open" class="modal-overlay" @mousedown.self="bankModal.open=false">
            <div class="modal-card">
                <header class="modal-header">
                    <h3>Bank details — {{ bankModal.recipient_name }}</h3>
                    <button class="close" @click="bankModal.open=false">&times;</button>
                </header>
                <div class="modal-body">
                    <label>Bank name *</label>
                    <input v-model="bankModal.bank_name" maxlength="120" />
                    <label>Branch</label>
                    <input v-model="bankModal.bank_branch" maxlength="120" />
                    <label>Account name *</label>
                    <input v-model="bankModal.bank_account_name" maxlength="120" />
                    <label>Account number *</label>
                    <input v-model="bankModal.bank_account_number" maxlength="50" />
                    <label>SWIFT / BIC</label>
                    <input v-model="bankModal.bank_swift_code" maxlength="20" />
                </div>
                <footer class="modal-footer">
                    <button class="btn-secondary" @click="bankModal.open=false">Cancel</button>
                    <button class="btn-primary" @click="saveBankForRecipient">Save</button>
                </footer>
            </div>
        </div>

        <!-- Thread modal -->
        <div v-if="threadModal.open" class="modal-overlay" @mousedown.self="closeThread">
            <div class="modal-card wide">
                <header class="modal-header"><h3>{{ threadModal.data?.subject }}</h3><button class="close" @click="closeThread">&times;</button></header>
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
                    <label class="inline"><input type="checkbox" v-model="threadModal.resolve" /> Mark thread as resolved</label>
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
    name: 'SuperPayouts',
    data() {
        return {
            tab: 'outstanding',
            loadingOutstanding: false,
            outstanding: [],
            outFilters: { role: '', currency: '', payment_method: '' },
            loadingHistory: false,
            history: [],
            histFilters: { status: '', currency: '', start_date: '', end_date: '' },
            loadingPL: false,
            pl: {
                gateway_profit: { by_currency: {}, by_method: [] },
                merchant_fees_to_admin: { by_currency: {}, by_method: [] },
                gross_payments_processed: { by_currency: {}, by_method: [] },
            },
            plFilters: { start_date: '', end_date: '', currency: '', payment_method: '' },
            qStatus: 'OPEN',
            loadingInbox: false,
            inbox: [],
            createModal: { open: false, recipient_user_id: null, recipientName: '', currency: '', estimatedTotal: 0, cutoff_date: '', notes: '' },
            sentModal: { open: false, payout: null, bank_reference: '', notes: '' },
            payoutModal: { open: false, data: null },
            threadModal: { open: false, data: null, reply: '', resolve: false },
            // schedules
            loadingSchedules: false,
            schedules: [],
            allRecipients: [],
            bankModal: {
                open: false,
                recipient_user_id: null,
                recipient_name: '',
                bank_name: '',
                bank_branch: '',
                bank_account_name: '',
                bank_account_number: '',
                bank_swift_code: '',
            },
            scheduleModal: {
                open: false,
                id: null,
                scope: 'all',
                recipient_user_id: null,
                recipient_role_scope: null,
                currency: 'USD',
                cadence: 'WEEKLY',
                day_of_week: 1,
                day_of_month: 1,
                minimum_amount: 0,
                cutoff_hours_back: 24,
                default_notes: '',
                is_active: true,
            },
        };
    },
    computed: {
        openQueriesCount() { return this.inbox.filter(t => t.status === 'OPEN').length; },
    },
    created() {
        this.bootstrap();
        this.loadOutstanding();
        this.loadInbox();
    },
    watch: {
        tab(val) {
            if (val === 'history') this.loadHistory();
            if (val === 'pl') this.loadPL();
            if (val === 'queries') this.loadInbox();
            if (val === 'schedules') { this.loadSchedules(); this.loadAllRecipients(); }
        },
    },
    methods: {
        bootstrap() {
            const token = localStorage.getItem('authToken');
            if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },
        formatAmount(v) { const n = Number(v); return isNaN(n) ? '0.00' : n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        formatDate(v) { if (!v) return '—'; return new Date(v).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); },
        formatMethod(m) {
            if (!m) return '—';
            const map = { VISA_MASTER: 'Visa/Master', ZIMSWITCH: 'Zimswitch', ECOCASH: 'EcoCash', OMARI: 'O’mari', INNBUCKS: 'InnBucks' };
            return map[m] || m;
        },
        recipientName(u) { if (!u) return '—'; return u.company_name || [u.first_name, u.last_name].filter(Boolean).join(' ') || u.email; },
        senderName(m) { const s = m.sender; if (!s) return 'Unknown'; return [s.first_name, s.last_name].filter(Boolean).join(' ') || s.email; },
        messageClass(m) { return m.sender_role === 'SUPER' ? 'from-super' : 'from-other'; },
        async loadOutstanding() {
            this.loadingOutstanding = true;
            try {
                const params = {};
                if (this.outFilters.role) params.role = this.outFilters.role;
                if (this.outFilters.currency) params.currency = this.outFilters.currency;
                if (this.outFilters.payment_method) params.payment_method = this.outFilters.payment_method;
                const { data } = await axios.get('/api/v1/super/payouts/outstanding', { params });
                this.outstanding = data?.data || [];
            } catch (e) {}
            finally { this.loadingOutstanding = false; }
        },
        async loadHistory() {
            this.loadingHistory = true;
            try {
                const params = {};
                Object.entries(this.histFilters).forEach(([k, v]) => { if (v) params[k] = v; });
                const { data } = await axios.get('/api/v1/super/payouts', { params });
                this.history = data?.data?.data || [];
            } catch (e) {}
            finally { this.loadingHistory = false; }
        },
        async loadPL() {
            this.loadingPL = true;
            try {
                const params = {};
                Object.entries(this.plFilters).forEach(([k, v]) => { if (v) params[k] = v; });
                const { data } = await axios.get('/api/v1/super/payouts/profit-loss', { params });
                this.pl = data?.data || this.pl;
            } catch (e) {}
            finally { this.loadingPL = false; }
        },
        async loadInbox() {
            this.loadingInbox = true;
            try {
                const { data } = await axios.get('/api/v1/super/payouts/messages', { params: { status: this.qStatus } });
                this.inbox = data?.data?.data || [];
            } catch (e) {}
            finally { this.loadingInbox = false; }
        },
        openCreatePayout(recipient, breakdown) {
            this.createModal = {
                open: true,
                recipient_user_id: recipient.user_id,
                recipientName: recipient.name,
                currency: breakdown.currency,
                estimatedTotal: breakdown.total,
                cutoff_date: '',
                notes: '',
            };
        },
        async createPayout() {
            try {
                const payload = {
                    recipient_user_id: this.createModal.recipient_user_id,
                    currency: this.createModal.currency,
                };
                if (this.createModal.cutoff_date) payload.cutoff_date = this.createModal.cutoff_date;
                if (this.createModal.notes) payload.notes = this.createModal.notes;
                await axios.post('/api/v1/super/payouts', payload);
                this.createModal.open = false;
                this.$swal?.fire('Created', 'Payout created in PENDING state.', 'success');
                this.loadOutstanding();
                if (this.tab === 'history') this.loadHistory();
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to create payout.', 'error');
            }
        },
        openMarkSent(payout) {
            this.sentModal = { open: true, payout, bank_reference: '', notes: payout.notes || '' };
        },
        async markSent() {
            try {
                await axios.post(`/api/v1/super/payouts/${this.sentModal.payout.id}/send`, {
                    bank_reference: this.sentModal.bank_reference,
                    notes: this.sentModal.notes,
                });
                this.sentModal.open = false;
                this.$swal?.fire('Sent', 'Payout marked as sent. Merchant has been notified.', 'success');
                this.loadHistory();
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to mark sent.', 'error');
            }
        },
        async openPayout(id) {
            try {
                const { data } = await axios.get(`/api/v1/super/payouts/${id}`);
                this.payoutModal = { open: true, data: data.data };
            } catch (e) {}
        },
        async openThread(id) {
            try {
                const { data } = await axios.get(`/api/v1/super/payouts/messages/${id}`);
                this.threadModal = { open: true, data: data.data, reply: '', resolve: false };
            } catch (e) {}
        },
        closeThread() { this.threadModal = { open: false, data: null, reply: '', resolve: false }; },
        async sendReply() {
            try {
                await axios.post(`/api/v1/super/payouts/messages/${this.threadModal.data.id}/reply`, {
                    body: this.threadModal.reply.trim(),
                    resolve: this.threadModal.resolve,
                });
                this.openThread(this.threadModal.data.id);
                this.loadInbox();
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to reply.', 'error');
            }
        },

        // -------- Schedules --------
        recipientLabel(u) {
            const name = u.company_name || [u.first_name, u.last_name].filter(Boolean).join(' ') || u.email;
            return `${name} (${u.email})`;
        },
        scheduleScopeLabel(s) {
            if (s.recipient_user_id && s.recipient) return this.recipientLabel(s.recipient);
            if (s.recipient_role_scope) return `All ${s.recipient_role_scope}`;
            return 'All recipients';
        },
        scheduleCadenceLabel(s) {
            if (s.cadence === 'WEEKLY') {
                const days = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                return `Weekly · ${days[s.day_of_week] || ''}`;
            }
            if (s.cadence === 'MONTHLY') return `Monthly · day ${s.day_of_month}`;
            return s.cadence;
        },
        async loadSchedules() {
            this.loadingSchedules = true;
            try {
                const { data } = await axios.get('/api/v1/super/payout-schedules');
                this.schedules = data?.data || [];
            } catch (e) {}
            finally { this.loadingSchedules = false; }
        },
        async loadAllRecipients() {
            if (this.allRecipients.length) return;
            try {
                // The outstanding endpoint already gives us every eligible recipient (ADMIN+MERCHANT).
                // We just need a flat list with id/email/role/company_name.
                const { data } = await axios.get('/api/v1/super/payouts/outstanding');
                const seen = new Set();
                const out = [];
                for (const r of (data?.data || [])) {
                    if (seen.has(r.user_id)) continue;
                    seen.add(r.user_id);
                    out.push({
                        id: r.user_id, email: r.email, role: r.role,
                        company_name: r.name, first_name: '', last_name: '',
                    });
                }
                this.allRecipients = out;
            } catch (e) {}
        },
        onScopeChange() {
            if (this.scheduleModal.scope !== 'single') this.scheduleModal.recipient_user_id = null;
        },
        openScheduleEditor(s = null) {
            if (s) {
                this.scheduleModal = {
                    open: true,
                    id: s.id,
                    scope: s.recipient_user_id ? 'single' : (s.recipient_role_scope ? `role-${s.recipient_role_scope.toLowerCase()}` : 'all'),
                    recipient_user_id: s.recipient_user_id,
                    recipient_role_scope: s.recipient_role_scope,
                    currency: s.currency,
                    cadence: s.cadence,
                    day_of_week: s.day_of_week || 1,
                    day_of_month: s.day_of_month || 1,
                    minimum_amount: Number(s.minimum_amount || 0),
                    cutoff_hours_back: s.cutoff_hours_back || 24,
                    default_notes: s.default_notes || '',
                    is_active: !!s.is_active,
                };
            } else {
                this.scheduleModal = {
                    open: true, id: null, scope: 'all',
                    recipient_user_id: null, recipient_role_scope: null,
                    currency: 'USD', cadence: 'WEEKLY', day_of_week: 1, day_of_month: 1,
                    minimum_amount: 0, cutoff_hours_back: 24, default_notes: '', is_active: true,
                };
            }
            this.loadAllRecipients();
        },
        buildSchedulePayload() {
            const m = this.scheduleModal;
            const payload = {
                currency: m.currency,
                cadence: m.cadence,
                minimum_amount: m.minimum_amount,
                cutoff_hours_back: m.cutoff_hours_back,
                default_notes: m.default_notes,
                is_active: m.is_active,
            };
            if (m.scope === 'single') {
                payload.recipient_user_id = m.recipient_user_id;
                payload.recipient_role_scope = null;
            } else if (m.scope === 'role-admin') {
                payload.recipient_user_id = null;
                payload.recipient_role_scope = 'ADMIN';
            } else if (m.scope === 'role-merchant') {
                payload.recipient_user_id = null;
                payload.recipient_role_scope = 'MERCHANT';
            } else {
                payload.recipient_user_id = null;
                payload.recipient_role_scope = null;
            }
            if (m.cadence === 'WEEKLY') payload.day_of_week = m.day_of_week;
            if (m.cadence === 'MONTHLY') payload.day_of_month = m.day_of_month;
            return payload;
        },
        async saveSchedule() {
            if (this.scheduleModal.scope === 'single' && !this.scheduleModal.recipient_user_id) {
                this.$swal?.fire('Pick a recipient', 'Select a specific user for the single-recipient scope.', 'warning');
                return;
            }
            try {
                const payload = this.buildSchedulePayload();
                if (this.scheduleModal.id) {
                    await axios.put(`/api/v1/super/payout-schedules/${this.scheduleModal.id}`, payload);
                } else {
                    await axios.post('/api/v1/super/payout-schedules', payload);
                }
                this.scheduleModal.open = false;
                this.loadSchedules();
                this.$swal?.fire('Saved', 'Schedule saved.', 'success');
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to save schedule.', 'error');
            }
        },
        async toggleActive(s, value) {
            try {
                await axios.put(`/api/v1/super/payout-schedules/${s.id}`, { is_active: value });
                this.loadSchedules();
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to toggle.', 'error');
            }
        },
        async runScheduleNow(s) {
            const ok = await this.$swal?.fire({
                title: `Run schedule #${s.id} now?`,
                text: 'This forces a run regardless of cadence and will create PENDING payouts for eligible recipients.',
                icon: 'question', showCancelButton: true,
            });
            if (!ok?.isConfirmed) return;
            try {
                const { data } = await axios.post(`/api/v1/super/payout-schedules/${s.id}/run-now`);
                this.loadSchedules();
                if (this.tab === 'history') this.loadHistory();
                this.$swal?.fire(
                    'Done',
                    `Created ${data.data?.payouts_created || 0} payouts totalling ${data.data?.currency || ''} ${data.data?.total_created_amount || 0}.`,
                    'success'
                );
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to run schedule.', 'error');
            }
        },
        maskAccount(num) {
            if (!num) return '—';
            const s = String(num);
            return s.length <= 4 ? s : '••••' + s.slice(-4);
        },
        async openBankEditor(recipient) {
            try {
                const { data } = await axios.get(`/api/v1/super/profile/${recipient.user_id}`);
                const p = data?.data || {};
                this.bankModal = {
                    open: true,
                    recipient_user_id: recipient.user_id,
                    recipient_name: recipient.name,
                    bank_name: p.bank_name || '',
                    bank_branch: p.bank_branch || '',
                    bank_account_name: p.bank_account_name || '',
                    bank_account_number: p.bank_account_number || '',
                    bank_swift_code: p.bank_swift_code || '',
                };
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to load profile.', 'error');
            }
        },
        async saveBankForRecipient() {
            try {
                await axios.put(`/api/v1/super/profile/${this.bankModal.recipient_user_id}`, {
                    bank_name: this.bankModal.bank_name || null,
                    bank_branch: this.bankModal.bank_branch || null,
                    bank_account_name: this.bankModal.bank_account_name || null,
                    bank_account_number: this.bankModal.bank_account_number || null,
                    bank_swift_code: this.bankModal.bank_swift_code || null,
                });
                this.bankModal.open = false;
                this.loadOutstanding();
                this.$swal?.fire('Saved', 'Bank details updated.', 'success');
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to save bank details.', 'error');
            }
        },
        async deleteSchedule(s) {
            const ok = await this.$swal?.fire({
                title: `Delete schedule #${s.id}?`,
                text: 'Existing payouts already created by this schedule are kept. Only the rule is removed.',
                icon: 'warning', showCancelButton: true,
            });
            if (!ok?.isConfirmed) return;
            try {
                await axios.delete(`/api/v1/super/payout-schedules/${s.id}`);
                this.loadSchedules();
            } catch (e) {
                this.$swal?.fire('Error', e.response?.data?.message || 'Failed to delete.', 'error');
            }
        },
    },
};
</script>

<style scoped>
.payouts-page { padding: 24px; max-width: 1500px; margin: 0 auto; color: #1e293b; }
.page-header h1 { margin: 0; font-size: 1.8rem; }
.page-header p { margin: 4px 0 0 0; color: #64748b; }
.tabs { display: flex; gap: 4px; margin: 20px 0; border-bottom: 1px solid #e2e8f0; }
.tab { padding: 10px 16px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #64748b; }
.tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }
.badge-count { background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: 6px; }
.card-section { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.filter-row { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-row select, .filter-row input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.88rem; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.data-table th, .data-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
.data-table .empty { text-align: center; color: #94a3b8; padding: 30px 0; }
.muted { color: #94a3b8; font-size: 0.8rem; }
.role-badge { padding: 3px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
.role-badge.admin { background: #ede9fe; color: #5b21b6; }
.role-badge.merchant { background: #cffafe; color: #155e75; }
.bal-line { margin-bottom: 8px; }
.bal-methods { color: #64748b; font-size: 0.8rem; display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
.bal-methods em { color: #94a3b8; font-style: normal; }
.btn-primary, .btn-secondary { padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 0.88rem; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary.small { padding: 6px 10px; font-size: 0.78rem; margin-right: 6px; margin-bottom: 4px; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #334155; }
.link-btn { background: none; border: none; color: #4f46e5; cursor: pointer; margin-right: 8px; font-weight: 600; }
.link-btn.success { color: #16a34a; }
.badge { padding: 3px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.badge.pending { background: #fef3c7; color: #92400e; }
.badge.sent { background: #dbeafe; color: #1e40af; }
.badge.confirmed { background: #dcfce7; color: #166534; }
.badge.disputed { background: #fee2e2; color: #991b1b; }
.badge.open { background: #dbeafe; color: #1e40af; }
.badge.resolved { background: #dcfce7; color: #166534; }
.loading, .empty { color: #94a3b8; padding: 20px 0; text-align: center; }
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: flex; align-items: center; justify-content: center; z-index: 5000; padding: 16px; }
.modal-card { background: #fff; border-radius: 12px; padding: 0; max-width: 520px; width: 100%; }
.modal-card.wide { max-width: 900px; }
.modal-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; }
.modal-header .close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
.modal-body { padding: 20px; }
.modal-body label { display: block; margin: 10px 0 4px 0; font-weight: 500; font-size: 0.85rem; color: #334155; }
.modal-body label.inline { display: flex; align-items: center; gap: 6px; }
.modal-body input, .modal-body textarea, .modal-body select { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }
.modal-footer.column { flex-direction: column; align-items: stretch; gap: 12px; }
.modal-footer .actions { display: flex; justify-content: flex-end; gap: 8px; }
.kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
.kv div { display: flex; flex-direction: column; }
.kv label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
.kv span { font-weight: 600; }
.pl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.pl-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
.pl-card.profit { background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; border: none; }
.pl-label { font-size: 0.85rem; font-weight: 500; margin-bottom: 6px; opacity: 0.85; }
.pl-amount { font-size: 1.4rem; font-weight: 700; }
.thread-body { max-height: 60vh; overflow-y: auto; }
.msg { background: #f8fafc; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
.msg.from-super { background: #eef2ff; }
.msg-meta { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
.msg p { margin: 0; white-space: pre-wrap; }
.spin { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.switch { position: relative; display: inline-block; width: 38px; height: 22px; }
.switch input { opacity: 0; width: 0; height: 0; }
.switch .slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 22px; transition: 0.2s; }
.switch .slider::before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; top: 3px; background: white; border-radius: 50%; transition: 0.2s; }
.switch input:checked + .slider { background: #4f46e5; }
.switch input:checked + .slider::before { transform: translateX(16px); }
.link-btn.danger { color: #dc2626; }
.bank-ok { font-size: 0.78rem; color: #166534; margin-top: 4px; }
.bank-missing { font-size: 0.78rem; color: #b91c1c; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
