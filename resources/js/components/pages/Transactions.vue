<template>
  <div class="transactions-container animate-fade-in">
    <div class="page-header">
      <h2>Transactions History</h2>
      <div class="filters">
        <div class="date-range">
          <input type="date" v-model="filters.startDate" class="date-input">
          <span>to</span>
          <input type="date" v-model="filters.endDate" class="date-input">
        </div>
        <select v-model="filters.status" class="status-filter">
          <option value="">All Status</option>
          <option value="COMPLETED">Completed</option>
          <option value="PENDING">Pending</option>
          <option value="FAILED">Failed</option>
        </select>
        <select v-model="filters.paymentMethod" class="status-filter">
          <option value="">All Methods</option>
          <option value="ECOCASH">EcoCash</option>
          <option value="OMARI">O'mari</option>
          <option value="INNBUCKS">InnBucks</option>
          <option value="ZIMSWITCH">ZimSwitch</option>
          <option value="VISA_MASTER">Visa/Master</option>
        </select>
        <button class="btn-primary" @click="applyFilters">
          <i class="ri-filter-3-line"></i>
          Apply
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-info">
          <h3>Volume (to Customer)</h3>
          <p class="amount">{{ formatAmount(stats.totalVolume) }}</p>
          <span class="stat-hint">Amount to send to customer</span>
        </div>
        <div class="stat-icon">
          <i class="ri-money-dollar-circle-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3>Total Profit (Charges)</h3>
          <p class="amount">{{ formatAmount(stats.totalProfit) }}</p>
          <span class="stat-hint">Our revenue from charges</span>
        </div>
        <div class="stat-icon">
          <i class="ri-line-chart-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3>Completed</h3>
          <p class="amount success">{{ stats.completed }}</p>
          <span class="trend positive">
            <i class="ri-arrow-up-line"></i>
            8.2%
          </span>
        </div>
        <div class="stat-icon success-bg">
          <i class="ri-checkbox-circle-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3>Pending</h3>
          <p class="amount warning">{{ stats.pending }}</p>
          <span class="trend neutral">
            <i class="ri-arrow-right-line"></i>
            0%
          </span>
        </div>
        <div class="stat-icon warning-bg">
          <i class="ri-time-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3>Failed</h3>
          <p class="amount danger">{{ stats.failed }}</p>
          <span class="trend negative">
            <i class="ri-arrow-down-line"></i>
            3.1%
          </span>
        </div>
        <div class="stat-icon danger-bg">
          <i class="ri-close-circle-line"></i>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="table-toolbar">
        <div class="search-box">
          <i class="ri-search-line"></i>
          <input
            v-model="search"
            type="text"
            placeholder="Search by ID, reference, email..."
            @input="handleSearch"
          >
          <button v-if="search" class="search-clear" @click="clearSearch">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <button class="btn-secondary" @click="exportTransactions">
          <i class="ri-download-line"></i>
          Export
        </button>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Date</th>
              <th>To Customer</th>
              <th>Our Charge</th>
              <th>Method</th>
              <th>Type</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading && transactions.length">
            <tr v-for="transaction in transactions" :key="transaction.id">
              <td class="txn-id">#{{ transaction.id }}</td>
              <td>{{ formatDate(transaction.created_at) }}</td>
              <td class="txn-amount">{{ formatAmount(transaction.amount) }}</td>
              <td class="txn-amount">{{ formatAmount(transaction.charge || 0) }}</td>
              <td>
                <span class="method-badge" :class="(transaction.payment_method || '').toLowerCase()">
                  {{ formatMethod(transaction.payment_method) }}
                </span>
              </td>
              <td>{{ transaction.type }}</td>
              <td>
                <span :class="['status-badge', transaction.status.toLowerCase()]">
                  {{ transaction.status }}
                </span>
              </td>
              <td class="actions">
                <button class="btn-icon" @click="viewDetails(transaction)" title="View Details">
                  <i class="ri-eye-line"></i>
                </button>
                <button v-if="isSuper" class="btn-icon btn-icon-audit" @click="viewAuditTrail(transaction)" title="Audit Trail">
                  <i class="ri-file-search-line"></i>
                </button>
                <button class="btn-icon" @click="downloadReceipt(transaction)" title="Download Receipt">
                  <i class="ri-file-list-3-line"></i>
                </button>
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="loading">
            <tr>
              <td colspan="8" class="text-center">
                <div class="loading-spinner">
                  <i class="ri-loader-4-line spin"></i>
                  Loading...
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="8" class="text-center empty-state">
                <i class="ri-inbox-line empty-icon"></i>
                <p>No transactions found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-container">
        <div class="pagination-info">
          Showing {{ startIndex }} to {{ endIndex }} of {{ totalItems }} entries
        </div>
        <div class="pagination">
          <button
            :disabled="currentPage === 1"
            @click="changePage(currentPage - 1)"
            class="pagination-btn"
          >
            Previous
          </button>
          <button
            v-for="page in visiblePages"
            :key="page"
            @click="changePage(page)"
            :class="['pagination-btn', { active: currentPage === page }]"
          >
            {{ page }}
          </button>
          <button
            :disabled="currentPage === totalPages"
            @click="changePage(currentPage + 1)"
            class="pagination-btn"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal" v-if="selectedTransaction" @click.self="selectedTransaction = null">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Transaction Details</h3>
          <button class="close-btn" @click="selectedTransaction = null">&times;</button>
        </div>
        <div class="modal-body">
          <div class="detail-grid">
            <div class="detail-item">
              <label>Transaction ID</label>
              <span>#{{ selectedTransaction.id }}</span>
            </div>
            <div class="detail-item">
              <label>Date</label>
              <span>{{ formatDate(selectedTransaction.created_at) }}</span>
            </div>
            <div class="detail-item">
              <label>To Customer</label>
              <span>{{ formatAmount(selectedTransaction.amount) }}</span>
            </div>
            <div class="detail-item">
              <label>Our Charge</label>
              <span>{{ formatAmount(selectedTransaction.charge || 0) }}</span>
            </div>
            <div class="detail-item">
              <label>Status</label>
              <span :class="['status-badge', selectedTransaction.status.toLowerCase()]">
                {{ selectedTransaction.status }}
              </span>
            </div>
            <div class="detail-item">
              <label>Type</label>
              <span>{{ selectedTransaction.type }}</span>
            </div>
            <div class="detail-item">
              <label>Customer</label>
              <span>{{ selectedTransaction.customer_name }}</span>
            </div>
          </div>
          <div class="timeline">
            <div class="timeline-item" v-for="(event, index) in selectedTransaction.timeline" :key="index">
              <div class="timeline-icon">
                <i :class="event.icon"></i>
              </div>
              <div class="timeline-content">
                <h4>{{ event.title }}</h4>
                <p>{{ event.description }}</p>
                <span class="timeline-time">{{ formatDate(event.timestamp) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="selectedTransaction = null">Close</button>
          <button class="btn-primary" @click="downloadReceipt(selectedTransaction)">
            <i class="ri-download-line"></i>
            Download Receipt
          </button>
        </div>
      </div>
    </div>

    <!-- Audit Trail Modal -->
    <div class="modal" v-if="auditModal.visible" @click.self="closeAuditModal">
      <div class="modal-content audit-modal">
        <div class="modal-header audit-header">
          <div class="audit-header-left">
            <h3><i class="ri-file-search-line"></i> Audit Trail</h3>
            <div class="audit-ref" v-if="auditModal.transaction">
              <span class="audit-ref-id">#{{ auditModal.transaction.id }}</span>
              <span class="audit-ref-code">{{ auditModal.transaction.reference }}</span>
            </div>
          </div>
          <button class="close-btn" @click="closeAuditModal">&times;</button>
        </div>

        <!-- Summary strip -->
        <div class="audit-summary" v-if="auditModal.transaction">
          <div class="audit-summary-item">
            <span class="audit-summary-label">Amount</span>
            <span class="audit-summary-value">{{ formatAmount(auditModal.transaction.amount) }}</span>
          </div>
          <div class="audit-summary-item">
            <span class="audit-summary-label">Method</span>
            <span class="method-badge sm" :class="(auditModal.transaction.payment_method || '').toLowerCase()">
              {{ formatMethod(auditModal.transaction.payment_method) }}
            </span>
          </div>
          <div class="audit-summary-item">
            <span class="audit-summary-label">Status</span>
            <span :class="['status-badge sm', (auditModal.transaction.status || '').toLowerCase()]">
              {{ auditModal.transaction.status }}
            </span>
          </div>
          <div class="audit-summary-item">
            <span class="audit-summary-label">Audit Events</span>
            <span class="audit-summary-value count">{{ auditModal.entries.length }}</span>
          </div>
        </div>

        <!-- Tabs -->
        <div class="audit-tabs">
          <button
            :class="['audit-tab', { active: auditModal.activeTab === 'timeline' }]"
            @click="auditModal.activeTab = 'timeline'"
          >
            <i class="ri-time-line"></i> Timeline
          </button>
          <button
            :class="['audit-tab', { active: auditModal.activeTab === 'payloads' }]"
            @click="auditModal.activeTab = 'payloads'"
          >
            <i class="ri-code-s-slash-line"></i> Request &amp; Response
          </button>
        </div>

        <div class="audit-body">
          <!-- Loading state -->
          <div v-if="auditModal.loading" class="audit-loading">
            <i class="ri-loader-4-line spin"></i>
            <span>Loading audit trail...</span>
          </div>

          <!-- Empty state -->
          <div v-else-if="!auditModal.entries.length" class="audit-empty">
            <i class="ri-inbox-line"></i>
            <p>No audit entries found for this transaction.</p>
          </div>

          <!-- Timeline Tab -->
          <div v-else-if="auditModal.activeTab === 'timeline'" class="audit-timeline">
            <div
              v-for="(entry, index) in auditModal.entries"
              :key="entry.id"
              class="audit-tl-item"
            >
              <div class="audit-tl-connector">
                <div :class="['audit-tl-dot', levelColor(entry.level)]"></div>
                <div class="audit-tl-line" v-if="index < auditModal.entries.length - 1"></div>
              </div>
              <div :class="['audit-tl-card', { 'error-border': entry.level === 'ERROR' }]">
                <div class="audit-tl-card-header">
                  <div class="audit-tl-badges">
                    <span :class="['level-badge', levelColor(entry.level)]">{{ entry.level }}</span>
                    <span class="stage-badge">{{ entry.stage }}</span>
                    <span class="provider-badge" v-if="entry.provider">{{ entry.provider }}</span>
                  </div>
                  <span class="audit-tl-time">{{ formatDateTime(entry.created_at) }}</span>
                </div>
                <div class="audit-tl-event">{{ formatEventName(entry.event) }}</div>
                <div class="audit-tl-meta" v-if="entry.endpoint">
                  <i class="ri-link"></i>
                  <span class="audit-endpoint">{{ entry.endpoint }}</span>
                </div>
                <div class="audit-tl-meta" v-if="entry.status_code">
                  <i class="ri-arrow-left-right-line"></i>
                  HTTP {{ entry.status_code }}
                </div>
                <div class="audit-tl-actions" v-if="entry.request_payload || entry.response_payload">
                  <button
                    class="audit-peek-btn"
                    @click="auditModal.activeTab = 'payloads'; scrollToEntry(entry.id)"
                  >
                    <i class="ri-code-s-slash-line"></i> View Payloads
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Payloads Tab -->
          <div v-else-if="auditModal.activeTab === 'payloads'" class="audit-payloads">
            <div
              v-for="entry in auditModal.entries"
              :key="'payload-' + entry.id"
              :ref="'entry-' + entry.id"
              class="payload-card"
            >
              <div class="payload-card-header" @click="togglePayloadCard(entry.id)">
                <div class="payload-card-title">
                  <span :class="['level-dot', levelColor(entry.level)]"></span>
                  <span class="payload-event">{{ formatEventName(entry.event) }}</span>
                  <span :class="['level-badge sm', levelColor(entry.level)]">{{ entry.level }}</span>
                  <span class="stage-badge sm">{{ entry.stage }}</span>
                </div>
                <div class="payload-card-right">
                  <span class="payload-time">{{ formatDateTime(entry.created_at) }}</span>
                  <i :class="['ri-arrow-down-s-line', { rotated: expandedPayloads[entry.id] }]"></i>
                </div>
              </div>

              <div class="payload-card-body" v-if="expandedPayloads[entry.id]">
                <div class="payload-meta-row" v-if="entry.provider || entry.endpoint || entry.status_code">
                  <span v-if="entry.provider" class="provider-badge">{{ entry.provider }}</span>
                  <span v-if="entry.status_code" :class="['http-badge', entry.status_code >= 400 ? 'error' : 'ok']">
                    HTTP {{ entry.status_code }}
                  </span>
                  <span v-if="entry.endpoint" class="endpoint-text">{{ entry.endpoint }}</span>
                </div>

                <div class="payload-panels">
                  <div class="payload-panel" v-if="entry.request_payload">
                    <div class="payload-panel-header">
                      <i class="ri-upload-2-line"></i> Request
                      <button class="copy-btn" @click="copyJson(entry.request_payload)" title="Copy JSON">
                        <i class="ri-file-copy-line"></i>
                      </button>
                    </div>
                    <pre class="json-viewer"><code>{{ formatJson(entry.request_payload) }}</code></pre>
                  </div>
                  <div class="payload-panel" v-if="entry.response_payload">
                    <div class="payload-panel-header">
                      <i class="ri-download-2-line"></i> Response
                      <button class="copy-btn" @click="copyJson(entry.response_payload)" title="Copy JSON">
                        <i class="ri-file-copy-line"></i>
                      </button>
                    </div>
                    <pre class="json-viewer"><code>{{ formatJson(entry.response_payload) }}</code></pre>
                  </div>
                  <div class="payload-panel" v-if="entry.meta_data">
                    <div class="payload-panel-header">
                      <i class="ri-information-line"></i> Metadata
                      <button class="copy-btn" @click="copyJson(entry.meta_data)" title="Copy JSON">
                        <i class="ri-file-copy-line"></i>
                      </button>
                    </div>
                    <pre class="json-viewer"><code>{{ formatJson(entry.meta_data) }}</code></pre>
                  </div>
                  <div class="no-payload" v-if="!entry.request_payload && !entry.response_payload && !entry.meta_data">
                    <i class="ri-file-forbid-line"></i>
                    No payload data for this event.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-secondary" @click="closeAuditModal">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Transactions',
  data() {
    return {
      loading: false,
      search: '',
      debounceTimer: null,
      filters: {
        startDate: '',
        endDate: '',
        status: '',
        paymentMethod: ''
      },
      stats: {
        totalVolume: 0,
        totalProfit: 0,
        completed: 0,
        pending: 0,
        failed: 0
      },
      transactions: [],
      selectedTransaction: null,
      currentPage: 1,
      itemsPerPage: 10,
      totalItems: 0,
      currency: 'USD',
      userRole: null,

      auditModal: {
        visible: false,
        loading: false,
        transaction: null,
        entries: [],
        activeTab: 'timeline'
      },
      expandedPayloads: {}
    }
  },
  computed: {
    isSuper() {
      return this.userRole === 'SUPER';
    },
    totalPages() {
      return Math.ceil(this.totalItems / this.itemsPerPage);
    },
    startIndex() {
      if (this.totalItems === 0) return 0;
      return (this.currentPage - 1) * this.itemsPerPage + 1;
    },
    endIndex() {
      return Math.min(this.startIndex + this.itemsPerPage - 1, this.totalItems);
    },
    visiblePages() {
      let pages = [];
      let startPage = Math.max(1, this.currentPage - 2);
      let endPage = Math.min(this.totalPages, startPage + 4);

      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }

      return pages;
    }
  },
  methods: {
    formatAmount(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: this.currency
      }).format(amount);
    },
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
    formatDateTime(date) {
      const d = new Date(date);
      return d.toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
    },
    formatMethod(method) {
      const map = {
        'ECOCASH': 'EcoCash',
        'OMARI': "O'mari",
        'INNBUCKS': 'InnBucks',
        'ZIMSWITCH': 'ZimSwitch',
        'VISA_MASTER': 'Visa/MC'
      };
      return map[method] || method || '—';
    },
    formatEventName(event) {
      return (event || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
    formatJson(data) {
      if (!data) return '—';
      if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch { return data; }
      }
      return JSON.stringify(data, null, 2);
    },
    levelColor(level) {
      const map = { 'ERROR': 'error', 'WARNING': 'warning', 'INFO': 'info', 'DEBUG': 'debug' };
      return map[level] || 'info';
    },

    handleSearch() {
      if (this.debounceTimer) clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.currentPage = 1;
        this.loadTransactions();
      }, 400);
    },
    clearSearch() {
      this.search = '';
      this.currentPage = 1;
      this.loadTransactions();
    },
    applyFilters() {
      this.currentPage = 1;
      this.loadTransactions();
    },

    async loadTransactions() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/transactions/all', {
          params: {
            search: this.search,
            page: this.currentPage,
            per_page: this.itemsPerPage,
            status: this.filters.status,
            payment_method: this.filters.paymentMethod,
            start_date: this.filters.startDate,
            end_date: this.filters.endDate,
            currency: this.currency
          }
        });

        if (response.data.success) {
          this.transactions = response.data.data.data;
          this.currentPage = response.data.data.current_page;
          this.totalItems = response.data.data.total;
          await this.loadTransactionStats();
        }
      } catch (error) {
        console.error('Error loading transactions:', error);
        this.$swal.fire('Error!', 'Failed to load transactions', 'error');
      } finally {
        this.loading = false;
      }
    },

    async loadTransactionStats() {
      try {
        const response = await axios.get('/api/v1/dashboard/stats', {
          params: { currency: this.currency }
        });

        if (response.data.success) {
          this.stats.totalVolume = response.data.data.totalVolume;
          this.stats.totalProfit = response.data.data.totalProfit ?? (response.data.data.systemCharges + response.data.data.merchantCharges);

          const statusResponse = await axios.get('/api/v1/transactions/all', {
            params: { count_by_status: true, currency: this.currency }
          });

          if (statusResponse.data.success) {
            const statusCounts = statusResponse.data.data;
            this.stats.completed = statusCounts.COMPLETED || 0;
            this.stats.pending = statusCounts.PENDING || 0;
            this.stats.failed = statusCounts.FAILED || 0;
          }
        }
      } catch (error) {
        console.error('Error loading transaction stats:', error);
      }
    },

    changePage(page) {
      this.currentPage = page;
      this.loadTransactions();
    },

    async viewDetails(transaction) {
      try {
        const response = await axios.get(`/api/v1/transactions/details/${transaction.id}`);

        if (response.data.success) {
          const transactionDetails = response.data.data;
          const timeline = this.generateTimeline(transactionDetails);
          this.selectedTransaction = {
            ...transactionDetails,
            timeline: timeline,
            customer_name: transactionDetails.user_name || 'N/A'
          };
        } else {
          this.$swal.fire('Error!', 'Failed to load transaction details', 'error');
        }
      } catch (error) {
        console.error('Error loading transaction details:', error);
        this.selectedTransaction = {
          ...transaction,
          timeline: this.generateTimeline(transaction),
          customer_name: transaction.user_name || 'N/A'
        };
      }
    },

    generateTimeline(transaction) {
      const timeline = [];
      timeline.push({
        title: 'Transaction Initiated',
        description: `${transaction.type} transaction started`,
        timestamp: transaction.created_at,
        icon: 'ri-play-circle-line'
      });

      if (transaction.status === 'COMPLETED') {
        timeline.push({
          title: 'Transaction Completed',
          description: 'Payment processed successfully',
          timestamp: transaction.updated_at,
          icon: 'ri-check-line'
        });
      } else if (transaction.status === 'FAILED') {
        timeline.push({
          title: 'Transaction Failed',
          description: transaction.error_message || 'Payment processing failed',
          timestamp: transaction.updated_at,
          icon: 'ri-close-circle-line'
        });
      } else if (transaction.status === 'PENDING') {
        timeline.push({
          title: 'Transaction Pending',
          description: 'Payment is being processed',
          timestamp: transaction.updated_at,
          icon: 'ri-time-line'
        });
      }

      return timeline;
    },

    // ─── Audit Trail ───
    async viewAuditTrail(transaction) {
      this.auditModal.visible = true;
      this.auditModal.loading = true;
      this.auditModal.transaction = transaction;
      this.auditModal.entries = [];
      this.auditModal.activeTab = 'timeline';
      this.expandedPayloads = {};

      try {
        const requests = [];

        if (transaction.trace) {
          requests.push(axios.get('/api/v1/transactions/audits', {
            params: { trace: transaction.trace, per_page: 100 }
          }));
        }

        if (transaction.reference) {
          requests.push(axios.get('/api/v1/transactions/audits', {
            params: { reference: transaction.reference, per_page: 100 }
          }));
        }

        if (!requests.length) {
          requests.push(axios.get('/api/v1/transactions/audits', {
            params: { transaction_id: transaction.id, per_page: 100 }
          }));
        }

        const responses = await Promise.all(requests);

        const seen = new Set();
        const merged = [];
        for (const resp of responses) {
          if (resp.data.success) {
            for (const entry of (resp.data.data.data || [])) {
              if (!seen.has(entry.id)) {
                seen.add(entry.id);
                merged.push(entry);
              }
            }
          }
        }

        this.auditModal.entries = merged.sort(
          (a, b) => new Date(a.created_at) - new Date(b.created_at)
        );
      } catch (error) {
        console.error('Error loading audit trail:', error);
        this.$swal.fire('Error!', 'Failed to load audit trail', 'error');
      } finally {
        this.auditModal.loading = false;
      }
    },

    closeAuditModal() {
      this.auditModal.visible = false;
      this.auditModal.transaction = null;
      this.auditModal.entries = [];
    },

    togglePayloadCard(entryId) {
      this.expandedPayloads = {
        ...this.expandedPayloads,
        [entryId]: !this.expandedPayloads[entryId]
      };
    },

    scrollToEntry(entryId) {
      this.$nextTick(() => {
        const refKey = 'entry-' + entryId;
        const el = this.$refs[refKey];
        if (el) {
          const target = Array.isArray(el) ? el[0] : el;
          if (target && target.$el) target.$el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          else if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        this.expandedPayloads = { ...this.expandedPayloads, [entryId]: true };
      });
    },

    async copyJson(data) {
      try {
        const text = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
        await navigator.clipboard.writeText(text);
        this.$swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1200 });
      } catch {
        this.$swal.fire('Error', 'Failed to copy', 'error');
      }
    },

    downloadReceipt(transaction) {
      try {
        const receiptData = {
          transaction_id: transaction.id,
          date: this.formatDate(transaction.created_at),
          amount: this.formatAmount(transaction.amount),
          status: transaction.status,
          type: transaction.type,
          customer: transaction.user_name || 'N/A',
          reference: transaction.reference || 'N/A'
        };

        axios.post('/api/v1/transactions/receipt', receiptData, { responseType: 'blob' })
          .then(response => {
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `receipt-${transaction.id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            this.$swal.fire('Success!', 'Receipt downloaded successfully', 'success');
          })
          .catch(error => {
            console.error('Error downloading receipt:', error);
            this.$swal.fire('Error!', 'Failed to download receipt', 'error');
          });
      } catch (error) {
        console.error('Error generating receipt:', error);
        this.$swal.fire('Error!', 'Failed to generate receipt', 'error');
      }
    },

    exportTransactions() {
      try {
        this.$swal.fire({
          title: 'Exporting...',
          text: 'Please wait while we generate your export file',
          allowOutsideClick: false,
          didOpen: () => { this.$swal.showLoading(); }
        });

        const params = {
          format: 'csv',
          status: this.filters.status,
          start_date: this.filters.startDate,
          end_date: this.filters.endDate,
          currency: this.currency
        };

        axios.get('/api/v1/transactions/export', { params, responseType: 'blob' })
          .then(response => {
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            const contentDisposition = response.headers['content-disposition'];
            let filename = 'transactions-export.csv';
            if (contentDisposition) {
              const filenameMatch = contentDisposition.match(/filename="(.+)"/);
              if (filenameMatch && filenameMatch.length === 2) filename = filenameMatch[1];
            }
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            this.$swal.fire('Success!', 'Transactions exported successfully', 'success');
          })
          .catch(error => {
            console.error('Error exporting transactions:', error);
            this.$swal.fire('Error!', 'Failed to export transactions', 'error');
          });
      } catch (error) {
        console.error('Error initiating export:', error);
        this.$swal.fire('Error!', 'Failed to initiate export', 'error');
      }
    }
  },
  mounted() {
    try {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      this.userRole = user.role || null;
    } catch { /* ignore */ }
    this.loadTransactions();
  }
}
</script>

<style scoped>
.transactions-container {
  animation: fadeIn 0.5s ease;
}

/* ─── Page Header & Filters ─── */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.filters {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-input {
  padding: 0.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.875rem;
}

.status-filter {
  padding: 0.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  min-width: 120px;
  font-size: 0.875rem;
  background: white;
}

/* ─── Stats Grid ─── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}

.stat-hint {
  font-size: 0.7rem;
  color: #999;
  display: block;
  margin-top: 0.25rem;
}

.stat-info h3 {
  color: #666;
  font-size: 0.875rem;
  margin: 0 0 0.5rem 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.amount {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 0;
  color: #010647;
}

.amount.success { color: #00b894; }
.amount.warning { color: #fdcb6e; }
.amount.danger { color: #ff4757; }

.stat-icon {
  background: linear-gradient(135deg, #010647 0%, #020968 100%);
  color: white;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.stat-icon.success-bg {
  background: linear-gradient(135deg, #00b894 0%, #00d1a7 100%);
}

.stat-icon.warning-bg {
  background: linear-gradient(135deg, #fdcb6e 0%, #ffd884 100%);
}

.stat-icon.danger-bg {
  background: linear-gradient(135deg, #ff4757 0%, #ff6b7d 100%);
}

/* ─── Table ─── */
.content-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  padding: 1.5rem;
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  gap: 1rem;
}

.search-box {
  display: flex;
  align-items: center;
  background: #f8f9fc;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  width: 340px;
  border: 2px solid #e2e8f0;
  transition: border-color 0.2s;
}

.search-box:focus-within {
  border-color: #010647;
}

.search-box input {
  border: none;
  outline: none;
  background: transparent;
  margin-left: 0.5rem;
  width: 100%;
  font-size: 0.875rem;
}

.search-clear {
  background: none;
  border: none;
  color: #999;
  cursor: pointer;
  padding: 0 0.25rem;
  font-size: 1rem;
  display: flex;
}

.search-clear:hover { color: #ff4757; }

.table-container { overflow-x: auto; }

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

th {
  background: #f8f9fc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #010647;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #f1f1f1;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #f1f1f1;
  transition: background 0.3s ease;
  font-size: 0.875rem;
}

tbody tr { transition: transform 0.3s ease; }

tbody tr:hover {
  background: #f8f9fc;
  transform: translateX(4px);
}

.txn-id {
  font-weight: 600;
  color: #010647;
}

.txn-amount {
  font-weight: 600;
}

/* ─── Badges ─── */
.status-badge {
  padding: 0.4rem 0.9rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.status-badge::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
}

.status-badge.completed { background: rgba(0,184,148,0.1); color: #00b894; }
.status-badge.completed::before { background: #00b894; }
.status-badge.pending { background: rgba(253,203,110,0.1); color: #e6a817; }
.status-badge.pending::before { background: #e6a817; }
.status-badge.failed { background: rgba(255,71,87,0.1); color: #ff4757; }
.status-badge.failed::before { background: #ff4757; }

.status-badge.sm { padding: 0.25rem 0.6rem; font-size: 0.7rem; }

.method-badge {
  padding: 0.3rem 0.7rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.method-badge.ecocash { background: #e8f5e9; color: #2e7d32; }
.method-badge.omari { background: #fff3e0; color: #e65100; }
.method-badge.innbucks { background: #e3f2fd; color: #1565c0; }
.method-badge.zimswitch { background: #fce4ec; color: #c62828; }
.method-badge.visa_master { background: #ede7f6; color: #4527a0; }
.method-badge.sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }

/* ─── Empty State ─── */
.empty-state {
  padding: 3rem 1rem !important;
  color: #999;
}

.empty-icon {
  font-size: 3rem;
  display: block;
  margin-bottom: 0.5rem;
  color: #ccc;
}

/* ─── Actions ─── */
.actions {
  white-space: nowrap;
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #f8f9fc;
  border: 1px solid #e2e8f0;
  color: #010647;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon:hover {
  background: #e2e8f0;
  transform: translateY(-1px);
}

.btn-icon-audit {
  background: #eef2ff;
  border-color: #c7d2fe;
  color: #4338ca;
}

.btn-icon-audit:hover {
  background: #c7d2fe;
}

.btn-icon i { font-size: 1.1rem; }

.btn-primary {
  background: #010647;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: background 0.2s;
}

.btn-primary:hover { background: #020968; }

.btn-secondary {
  background: #f8f9fc;
  color: #010647;
  border: 2px solid #e2e8f0;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-secondary:hover { background: #e2e8f0; }

/* ─── Pagination ─── */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f1f1;
}

.pagination-info {
  font-size: 0.85rem;
  color: #666;
}

.pagination { display: flex; gap: 0.5rem; }

.pagination-btn {
  background: #f8f9fc;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.85rem;
}

.pagination-btn:hover:not(:disabled) { background: #e2e8f0; }

.pagination-btn.active {
  background: #010647;
  color: white;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ─── Base Modal ─── */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.2s ease;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  animation: scaleIn 0.25s ease;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 2px solid #f1f1f1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  background: white;
  z-index: 2;
  border-radius: 12px 12px 0 0;
}

.modal-body { padding: 1.5rem; }

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 2px solid #f1f1f1;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  position: sticky;
  bottom: 0;
  background: white;
  border-radius: 0 0 12px 12px;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.75rem;
  color: #999;
  cursor: pointer;
  line-height: 1;
  padding: 0;
  transition: color 0.2s;
}

.close-btn:hover { color: #ff4757; }

.detail-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.detail-item label {
  color: #666;
  font-size: 0.875rem;
  font-weight: 500;
}

/* ─── Basic Timeline (transaction details) ─── */
.timeline {
  position: relative;
  padding: 2rem 0;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 16px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #f1f1f1;
}

.timeline-item {
  position: relative;
  padding-left: 45px;
  margin-bottom: 1.5rem;
}

.timeline-icon {
  position: absolute;
  left: 0;
  width: 35px;
  height: 35px;
  background: #f8f9fc;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #010647;
  border: 2px solid #f1f1f1;
}

.timeline-content {
  background: #f8f9fc;
  border-radius: 8px;
  padding: 1rem;
}

.timeline-content h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  color: #010647;
}

.timeline-content p { margin: 0; color: #666; }

.timeline-time {
  font-size: 0.875rem;
  color: #999;
  margin-top: 0.5rem;
  display: block;
}

/* ════════════════════════════════════════════════
   AUDIT TRAIL MODAL
   ════════════════════════════════════════════════ */

.audit-modal {
  max-width: 960px;
  display: flex;
  flex-direction: column;
}

.audit-header {
  background: #010647;
  color: white;
  border-radius: 12px 12px 0 0;
}

.audit-header .close-btn { color: rgba(255,255,255,0.6); }
.audit-header .close-btn:hover { color: white; }

.audit-header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.audit-header-left h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.1rem;
}

.audit-ref {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.audit-ref-id {
  background: rgba(255,255,255,0.15);
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
}

.audit-ref-code {
  font-size: 0.8rem;
  opacity: 0.75;
  font-family: monospace;
}

/* ─── Audit Summary Strip ─── */
.audit-summary {
  display: flex;
  gap: 1.5rem;
  padding: 0.9rem 1.5rem;
  background: #f8f9fc;
  border-bottom: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

.audit-summary-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.audit-summary-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #999;
  font-weight: 600;
}

.audit-summary-value {
  font-size: 0.95rem;
  font-weight: 700;
  color: #010647;
}

.audit-summary-value.count {
  background: #eef2ff;
  color: #4338ca;
  padding: 0.1rem 0.5rem;
  border-radius: 10px;
  font-size: 0.8rem;
}

/* ─── Audit Tabs ─── */
.audit-tabs {
  display: flex;
  border-bottom: 2px solid #e2e8f0;
  padding: 0 1.5rem;
  background: white;
  position: sticky;
  top: 60px;
  z-index: 1;
}

.audit-tab {
  padding: 0.75rem 1.25rem;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 500;
  color: #666;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}

.audit-tab:hover { color: #010647; }

.audit-tab.active {
  color: #010647;
  border-bottom-color: #010647;
  font-weight: 600;
}

/* ─── Audit Body ─── */
.audit-body {
  padding: 1.5rem;
  max-height: 55vh;
  overflow-y: auto;
}

.audit-loading, .audit-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: #999;
  gap: 0.75rem;
}

.audit-loading i, .audit-empty i { font-size: 2.5rem; color: #ccc; }

/* ─── Audit Timeline ─── */
.audit-timeline { position: relative; }

.audit-tl-item {
  display: flex;
  gap: 1rem;
  min-height: 80px;
}

.audit-tl-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 20px;
  flex-shrink: 0;
}

.audit-tl-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
  border: 3px solid #a5b4fc;
  background: white;
}

.audit-tl-dot.info { border-color: #60a5fa; }
.audit-tl-dot.error { border-color: #f87171; background: #fef2f2; }
.audit-tl-dot.warning { border-color: #fbbf24; background: #fffbeb; }
.audit-tl-dot.debug { border-color: #a78bfa; }

.audit-tl-line {
  width: 2px;
  flex: 1;
  background: #e2e8f0;
  margin: 4px 0;
}

.audit-tl-card {
  flex: 1;
  background: #f8f9fc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.85rem 1rem;
  margin-bottom: 0.75rem;
  transition: all 0.2s;
}

.audit-tl-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.audit-tl-card.error-border {
  border-left: 3px solid #f87171;
  background: #fef8f8;
}

.audit-tl-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.4rem;
  flex-wrap: wrap;
}

.audit-tl-badges {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.level-badge {
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.level-badge.info { background: #dbeafe; color: #1d4ed8; }
.level-badge.error { background: #fee2e2; color: #dc2626; }
.level-badge.warning { background: #fef3c7; color: #d97706; }
.level-badge.debug { background: #ede9fe; color: #7c3aed; }
.level-badge.sm { font-size: 0.6rem; padding: 0.1rem 0.35rem; }

.stage-badge {
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.65rem;
  font-weight: 600;
  background: #f1f5f9;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.stage-badge.sm { font-size: 0.6rem; padding: 0.1rem 0.35rem; }

.provider-badge {
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.65rem;
  font-weight: 600;
  background: #ecfdf5;
  color: #059669;
  text-transform: uppercase;
}

.audit-tl-time {
  font-size: 0.7rem;
  color: #94a3b8;
  white-space: nowrap;
}

.audit-tl-event {
  font-size: 0.85rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.3rem;
}

.audit-tl-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  color: #64748b;
  margin-bottom: 0.15rem;
}

.audit-tl-meta i { font-size: 0.8rem; }

.audit-endpoint {
  word-break: break-all;
  font-family: monospace;
  font-size: 0.7rem;
}

.audit-tl-actions { margin-top: 0.5rem; }

.audit-peek-btn {
  background: none;
  border: 1px solid #c7d2fe;
  color: #4338ca;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 500;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  transition: all 0.2s;
}

.audit-peek-btn:hover {
  background: #eef2ff;
}

/* ─── Payloads Tab ─── */
.audit-payloads {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.payload-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}

.payload-card:hover {
  box-shadow: 0 1px 6px rgba(0,0,0,0.05);
}

.payload-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.7rem 1rem;
  background: #f8f9fc;
  cursor: pointer;
  user-select: none;
  gap: 0.5rem;
}

.payload-card-header:hover { background: #f1f5f9; }

.payload-card-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.level-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.level-dot.info { background: #60a5fa; }
.level-dot.error { background: #f87171; }
.level-dot.warning { background: #fbbf24; }
.level-dot.debug { background: #a78bfa; }

.payload-event {
  font-size: 0.8rem;
  font-weight: 600;
  color: #1e293b;
}

.payload-card-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.payload-time {
  font-size: 0.7rem;
  color: #94a3b8;
}

.payload-card-right i {
  transition: transform 0.2s;
  color: #94a3b8;
  font-size: 1.2rem;
}

.payload-card-right i.rotated {
  transform: rotate(180deg);
}

.payload-card-body {
  padding: 0.75rem 1rem 1rem;
  border-top: 1px solid #e2e8f0;
  animation: slideDown 0.2s ease;
}

.payload-meta-row {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}

.http-badge {
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.65rem;
  font-weight: 700;
}

.http-badge.ok { background: #dcfce7; color: #16a34a; }
.http-badge.error { background: #fee2e2; color: #dc2626; }

.endpoint-text {
  font-family: monospace;
  font-size: 0.7rem;
  color: #64748b;
  word-break: break-all;
}

.payload-panels {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.payload-panel {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.payload-panel-header {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.75rem;
  background: #f1f5f9;
  font-size: 0.72rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.copy-btn {
  margin-left: auto;
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.15rem;
  display: flex;
  transition: color 0.2s;
}

.copy-btn:hover { color: #4338ca; }

.json-viewer {
  margin: 0;
  padding: 0.75rem;
  background: #fafbfd;
  font-size: 0.72rem;
  line-height: 1.5;
  overflow-x: auto;
  max-height: 320px;
  font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', Menlo, monospace;
  color: #334155;
  white-space: pre-wrap;
  word-break: break-word;
}

.no-payload {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  color: #94a3b8;
  font-size: 0.8rem;
}

/* ─── Animations ─── */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes scaleIn {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

@keyframes slideDown {
  from { opacity: 0; max-height: 0; }
  to { opacity: 1; max-height: 1000px; }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.spin { animation: spin 1s linear infinite; }

.loading-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #666;
  padding: 2rem;
}

.text-center { text-align: center; }

/* ─── Responsive ─── */
@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .detail-grid { grid-template-columns: repeat(2, 1fr); }
  .audit-modal { max-width: 95%; }
}

@media (max-width: 768px) {
  .page-header { flex-direction: column; align-items: stretch; }
  .filters { flex-direction: column; align-items: stretch; }
  .stats-grid { grid-template-columns: 1fr; }
  .detail-grid { grid-template-columns: 1fr; }
  .pagination-container { flex-direction: column; gap: 1rem; align-items: center; }
  .search-box { width: 100%; }
  .table-toolbar { flex-direction: column; align-items: stretch; }
  .audit-summary { gap: 0.75rem; }
  .audit-body { padding: 1rem; }
}
</style>
