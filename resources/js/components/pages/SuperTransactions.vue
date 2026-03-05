<template>
  <div class="super-transactions-container">
    <div class="page-header">
      <h1><i class="ri-exchange-dollar-line"></i> All Gateway Transactions</h1>
      <div class="filters">
        <div class="date-range">
          <input type="date" v-model="filters.start_date" class="date-input" />
          <span>to</span>
          <input type="date" v-model="filters.end_date" class="date-input" />
        </div>
        <select v-model="filters.status" class="filter-select">
          <option value="">All</option>
          <option value="COMPLETED">COMPLETED</option>
          <option value="PENDING">PENDING</option>
          <option value="FAILED">FAILED</option>
        </select>
        <select v-model="filters.payment_method" class="filter-select">
          <option value="">All</option>
          <option value="ECOCASH">ECOCASH</option>
          <option value="OMARI">OMARI</option>
          <option value="INNBUCKS">INNBUCKS</option>
          <option value="ZIMSWITCH">ZIMSWITCH</option>
          <option value="VISA_MASTER">VISA_MASTER</option>
        </select>
        <button class="btn-apply" @click="applyFilters">
          <i class="ri-filter-3-line"></i> Apply
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-cards" v-if="stats">
      <div class="stat-card">
        <div class="stat-info">
          <h3>Volume (to Customer)</h3>
          <p class="stat-value">{{ formatAmount(stats.totalVolume, 'USD') }}</p>
          <span class="stat-hint">Amount to send to customer</span>
        </div>
        <div class="stat-icon"><i class="ri-money-dollar-circle-line"></i></div>
      </div>
      <div class="stat-card accent">
        <div class="stat-info">
          <h3>Total Profit (Charges)</h3>
          <p class="stat-value">{{ formatAmount(stats.totalProfit, 'USD') }}</p>
          <span class="stat-hint">Our revenue from charges</span>
        </div>
        <div class="stat-icon"><i class="ri-line-chart-line"></i></div>
      </div>
    </div>

    <div class="content-card">
      <div class="table-toolbar">
        <div class="search-box">
          <i class="ri-search-line"></i>
          <input
            v-model="search"
            type="text"
            placeholder="Search by ID, reference, user, merchant..."
            @input="handleSearch"
          />
        </div>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>To Customer</th>
              <th>Our Charge</th>
              <th>Method</th>
              <th>Company</th>
              <th>Merchant</th>
              <th>User</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading && transactions.length">
            <tr v-for="txn in transactions" :key="txn.id">
              <td class="txn-id">#{{ txn.id }}</td>
              <td>{{ formatDate(txn.created_at) }}</td>
              <td class="txn-amount">{{ formatAmount(txn.amount, txn.currency) }}</td>
              <td class="txn-amount">{{ formatAmount(txn.charge ?? 0, txn.currency) }}</td>
              <td>
                <span class="method-badge" :class="methodClass(txn.payment_method)">
                  {{ formatMethod(txn.payment_method) }}
                </span>
              </td>
              <td>{{ txn.company_name || '—' }}</td>
              <td>{{ merchantName(txn) }}</td>
              <td>{{ txn.user_name || userEmail(txn) || '—' }}</td>
              <td>
                <span :class="['status-badge', statusClass(txn.status)]">
                  {{ txn.status }}
                </span>
              </td>
              <td class="actions">
                <button class="btn-icon" @click="viewDetails(txn)" title="View details">
                  <i class="ri-eye-line"></i>
                </button>
                <button class="btn-icon btn-icon-audit" @click="viewAuditTrail(txn)" title="Audit Trail">
                  <i class="ri-file-search-line"></i>
                </button>
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="loading">
            <tr>
              <td colspan="10" class="text-center">
                <div class="loading-spinner">
                  <i class="ri-loader-4-line spin"></i> Loading...
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="10" class="empty-state">
                <i class="ri-inbox-line empty-icon"></i>
                <p>No transactions found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

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
            :disabled="currentPage === lastPage"
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
      <div class="modal-content details-modal">
        <div class="modal-header">
          <h3><i class="ri-eye-line"></i> Transaction Details</h3>
          <button class="close-btn" @click="selectedTransaction = null">&times;</button>
        </div>
        <div class="modal-body">
          <div class="detail-grid">
            <div class="detail-item">
              <label>ID</label>
              <span>#{{ selectedTransaction.id }}</span>
            </div>
            <div class="detail-item">
              <label>Date</label>
              <span>{{ formatDate(selectedTransaction.created_at) }}</span>
            </div>
            <div class="detail-item">
              <label>To Customer</label>
              <span>{{ formatAmount(selectedTransaction.amount, selectedTransaction.currency) }}</span>
            </div>
            <div class="detail-item">
              <label>Our Charge</label>
              <span>{{ formatAmount(selectedTransaction.charge ?? 0, selectedTransaction.currency) }}</span>
            </div>
            <div class="detail-item">
              <label>Currency</label>
              <span>{{ selectedTransaction.currency || 'USD' }}</span>
            </div>
            <div class="detail-item">
              <label>Payment Method</label>
              <span :class="['method-badge sm', methodClass(selectedTransaction.payment_method)]">
                {{ formatMethod(selectedTransaction.payment_method) }}
              </span>
            </div>
            <div class="detail-item">
              <label>Status</label>
              <span :class="['status-badge sm', statusClass(selectedTransaction.status)]">
                {{ selectedTransaction.status }}
              </span>
            </div>
            <div class="detail-item">
              <label>Type</label>
              <span>{{ selectedTransaction.type || '—' }}</span>
            </div>
            <div class="detail-item">
              <label>Reference</label>
              <span>{{ selectedTransaction.reference || '—' }}</span>
            </div>
            <div class="detail-item">
              <label>Trace</label>
              <span>{{ selectedTransaction.trace || '—' }}</span>
            </div>
            <div class="detail-item">
              <label>Company</label>
              <span>{{ selectedTransaction.company_name || '—' }}</span>
            </div>
            <div class="detail-item">
              <label>User</label>
              <span>{{ selectedTransaction.user_name || userEmail(selectedTransaction) || '—' }}</span>
            </div>
            <div class="detail-item">
              <label>User Email</label>
              <span>{{ userEmail(selectedTransaction) || '—' }}</span>
            </div>
            <div class="detail-item">
              <label>Merchant</label>
              <span>{{ merchantName(selectedTransaction) }}</span>
            </div>
            <div class="detail-item">
              <label>Merchant UID</label>
              <span>{{ selectedTransaction.merchant_uid || '—' }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="selectedTransaction = null">Close</button>
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
          <button class="close-btn audit-close" @click="closeAuditModal">&times;</button>
        </div>

        <div class="audit-summary" v-if="auditModal.transaction">
          <div class="audit-summary-item">
            <span class="audit-summary-label">Amount</span>
            <span class="audit-summary-value">{{ formatAmount(auditModal.transaction.amount, auditModal.transaction.currency) }}</span>
          </div>
          <div class="audit-summary-item">
            <span class="audit-summary-label">Method</span>
            <span class="method-badge sm" :class="methodClass(auditModal.transaction.payment_method)">
              {{ formatMethod(auditModal.transaction.payment_method) }}
            </span>
          </div>
          <div class="audit-summary-item">
            <span class="audit-summary-label">Status</span>
            <span :class="['status-badge sm', statusClass(auditModal.transaction.status)]">
              {{ auditModal.transaction.status }}
            </span>
          </div>
          <div class="audit-summary-item">
            <span class="audit-summary-label">Audit Events</span>
            <span class="audit-summary-value count">{{ auditModal.entries.length }}</span>
          </div>
        </div>

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
          <div v-if="auditModal.loading" class="audit-loading">
            <i class="ri-loader-4-line spin"></i>
            <span>Loading audit trail...</span>
          </div>

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
  name: 'SuperTransactions',
  data() {
    return {
      loading: false,
      search: '',
      debounceTimer: null,
      filters: {
        start_date: '',
        end_date: '',
        status: '',
        payment_method: ''
      },
      transactions: [],
      selectedTransaction: null,
      currentPage: 1,
      perPage: 15,
      totalItems: 0,
      lastPage: 1,

      auditModal: {
        visible: false,
        loading: false,
        transaction: null,
        entries: [],
        activeTab: 'timeline'
      },
      expandedPayloads: {},
      stats: {
        totalVolume: 0,
        totalProfit: 0
      }
    };
  },
  computed: {
    startIndex() {
      if (this.totalItems === 0) return 0;
      return (this.currentPage - 1) * this.perPage + 1;
    },
    endIndex() {
      return Math.min(this.startIndex + this.perPage - 1, this.totalItems);
    },
    visiblePages() {
      const pages = [];
      const start = Math.max(1, this.currentPage - 2);
      const end = Math.min(this.lastPage, start + 4);
      for (let i = start; i <= end; i++) pages.push(i);
      return pages;
    }
  },
  mounted() {
    this.loadTransactions();
    this.loadStats();
  },
  methods: {
    formatAmount(amount, currency = 'USD') {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'USD'
      }).format(amount || 0);
    },
    formatDate(date) {
      if (!date) return '—';
      return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    formatMethod(method) {
      const map = {
        ECOCASH: 'EcoCash',
        OMARI: "O'mari",
        INNBUCKS: 'InnBucks',
        ZIMSWITCH: 'ZimSwitch',
        VISA_MASTER: 'Visa/Master'
      };
      return map[method] || method || '—';
    },
    methodClass(method) {
      const m = (method || '').toLowerCase().replace('-', '_');
      return m || 'default';
    },
    statusClass(status) {
      return (status || '').toLowerCase();
    },
    merchantName(txn) {
      if (txn.merchant) {
        return txn.merchant.merchant_name || txn.merchant.merchant_email || '—';
      }
      return '—';
    },
    userEmail(txn) {
      if (txn.user) return txn.user.email;
      return null;
    },
    handleSearch() {
      if (this.debounceTimer) clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.currentPage = 1;
        this.loadTransactions();
      }, 400);
    },
    applyFilters() {
      this.currentPage = 1;
      this.loadTransactions();
    },
    async loadStats() {
      try {
        const res = await axios.get('/api/v1/super/dashboard/stats');
        if (res.data.success) {
          this.stats = {
            totalVolume: res.data.data.totalVolume ?? 0,
            totalProfit: res.data.data.totalProfit ?? 0
          };
        }
      } catch (err) {
        console.error('Error loading stats:', err);
      }
    },
    async loadTransactions() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/super/transactions', {
          params: {
            search: this.search,
            status: this.filters.status,
            payment_method: this.filters.payment_method,
            start_date: this.filters.start_date,
            end_date: this.filters.end_date,
            page: this.currentPage,
            per_page: this.perPage
          }
        });
        if (response.data.success) {
          const data = response.data.data;
          this.transactions = data.data || [];
          this.currentPage = data.current_page || 1;
          this.totalItems = data.total || 0;
          this.lastPage = data.last_page || 1;
        }
      } catch (error) {
        this.$swal.fire('Error!', 'Failed to load transactions', 'error');
      } finally {
        this.loading = false;
      }
    },
    changePage(page) {
      this.currentPage = page;
      this.loadTransactions();
    },
    viewDetails(txn) {
      this.selectedTransaction = { ...txn };
    },

    formatDateTime(date) {
      const d = new Date(date);
      return d.toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
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

    async viewAuditTrail(txn) {
      this.auditModal.visible = true;
      this.auditModal.loading = true;
      this.auditModal.transaction = txn;
      this.auditModal.entries = [];
      this.auditModal.activeTab = 'timeline';
      this.expandedPayloads = {};

      try {
        const requests = [];

        if (txn.trace) {
          requests.push(axios.get('/api/v1/transactions/audits', {
            params: { trace: txn.trace, per_page: 100 }
          }));
        }

        if (txn.reference) {
          requests.push(axios.get('/api/v1/transactions/audits', {
            params: { reference: txn.reference, per_page: 100 }
          }));
        }

        if (!requests.length) {
          requests.push(axios.get('/api/v1/transactions/audits', {
            params: { transaction_id: txn.id, per_page: 100 }
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
    }
  }
};
</script>

<style scoped>
.super-transactions-container {
  animation: fadeIn 0.5s ease;
  min-height: 100%;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-header h1 i {
  color: #f59e0b;
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

.date-input,
.filter-select {
  padding: 0.5rem 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  background: #fff;
}

.filter-select {
  min-width: 120px;
}

.btn-apply {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #0f172a;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-apply:hover {
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stats-cards .stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
  border: 1px solid rgba(15, 23, 42, 0.06);
}

.stats-cards .stat-card.accent {
  border-left: 4px solid #f59e0b;
}

.stats-cards .stat-info h3 {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 0.25rem 0;
}

.stats-cards .stat-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.stats-cards .stat-hint {
  font-size: 0.7rem;
  color: #94a3b8;
  display: block;
  margin-top: 0.25rem;
}

.stats-cards .stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  color: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.content-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
  border: 1px solid rgba(15, 23, 42, 0.06);
  padding: 1.5rem;
}

.table-toolbar {
  margin-bottom: 1rem;
}

.search-box {
  display: flex;
  align-items: center;
  background: #f8fafc;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  max-width: 360px;
  border: 2px solid #e2e8f0;
}

.search-box:focus-within {
  border-color: #f59e0b;
}

.search-box i {
  color: #64748b;
  margin-right: 0.5rem;
}

.search-box input {
  border: none;
  outline: none;
  background: transparent;
  flex: 1;
  font-size: 0.875rem;
}

.table-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  padding: 1rem;
  text-align: left;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  font-weight: 600;
  color: #0f172a;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.875rem;
}

tbody tr:hover td {
  background: #f8fafc;
}

.txn-id {
  font-weight: 600;
  color: #0f172a;
}

.txn-amount {
  font-weight: 600;
}

/* Method badges */
.method-badge {
  padding: 0.3rem 0.65rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.method-badge.ecocash {
  background: rgba(34, 197, 94, 0.15);
  color: #16a34a;
}

.method-badge.omari {
  background: rgba(249, 115, 22, 0.15);
  color: #ea580c;
}

.method-badge.innbucks {
  background: rgba(59, 130, 246, 0.15);
  color: #2563eb;
}

.method-badge.zimswitch {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

.method-badge.visa_master {
  background: rgba(139, 92, 246, 0.15);
  color: #7c3aed;
}

.method-badge.default {
  background: #f1f5f9;
  color: #64748b;
}

.method-badge.sm {
  padding: 0.2rem 0.5rem;
  font-size: 0.7rem;
}

/* Status badges */
.status-badge {
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-badge.completed {
  background: rgba(34, 197, 94, 0.15);
  color: #16a34a;
}

.status-badge.pending {
  background: rgba(245, 158, 11, 0.2);
  color: #d97706;
}

.status-badge.failed {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

.status-badge.sm {
  padding: 0.25rem 0.6rem;
  font-size: 0.7rem;
}

.actions {
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
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #0f172a;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: #e2e8f0;
  color: #f59e0b;
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

.loading-spinner {
  padding: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #64748b;
}

.empty-state {
  padding: 3rem 1rem !important;
  text-align: center;
  color: #64748b;
}

.empty-icon {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.5rem;
  color: #94a3b8;
}

.text-center {
  text-align: center;
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
  flex-wrap: wrap;
  gap: 1rem;
}

.pagination-info {
  font-size: 0.875rem;
  color: #64748b;
}

.pagination {
  display: flex;
  gap: 0.5rem;
}

.pagination-btn {
  background: #f1f5f9;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.875rem;
  color: #334155;
  transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
  background: #e2e8f0;
}

.pagination-btn.active {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #0f172a;
  font-weight: 600;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.details-modal {
  max-width: 720px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: 90%;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-header h3 i {
  color: #f59e0b;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #64748b;
  cursor: pointer;
  line-height: 1;
  padding: 0;
}

.close-btn:hover {
  color: #0f172a;
}

.modal-body {
  padding: 1.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.detail-item label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-item span {
  font-size: 0.9rem;
  color: #0f172a;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.btn-secondary {
  background: #f1f5f9;
  color: #334155;
  border: none;
  padding: 0.6rem 1.25rem;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 1024px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
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

.audit-close { color: rgba(255,255,255,0.6); }
.audit-close:hover { color: white; }

.audit-header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.audit-header-left h3 {
  margin: 0;
  color: white;
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
  color: white;
}

.audit-ref-code {
  font-size: 0.8rem;
  opacity: 0.75;
  font-family: monospace;
  color: white;
}

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

@keyframes slideDown {
  from { opacity: 0; max-height: 0; }
  to { opacity: 1; max-height: 1000px; }
}

@media (max-width: 1024px) {
  .audit-modal { max-width: 95%; }
}

@media (max-width: 768px) {
  .page-header h1 {
    font-size: 1.25rem;
  }

  .filters {
    flex-direction: column;
    align-items: stretch;
  }

  .date-range {
    flex-wrap: wrap;
  }

  .pagination-container {
    flex-direction: column;
    align-items: center;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .audit-summary { gap: 0.75rem; }
  .audit-body { padding: 1rem; }
}
</style>
