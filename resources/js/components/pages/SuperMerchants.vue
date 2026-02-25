<template>
  <div class="super-merchants">
    <div class="page-header">
      <h1><i class="ri-store-2-line"></i> All Merchants</h1>
      <div class="header-actions">
        <div class="search-box">
          <i class="ri-search-line"></i>
          <input
            v-model="search"
            type="text"
            placeholder="Search merchants..."
            @input="handleSearch"
          />
          <button v-if="search" class="search-clear" @click="clearSearch">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <select v-model="statusFilter" class="status-filter" @change="fetchMerchants">
          <option value="">All</option>
          <option value="ACTIVE">Active</option>
          <option value="INACTIVE">Inactive</option>
          <option value="DEVELOPMENT">Development</option>
        </select>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <i class="ri-store-3-line"></i>
        </div>
        <div class="stat-content">
          <span class="stat-value">{{ totalMerchants }}</span>
          <span class="stat-label">Total Merchants</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon active">
          <i class="ri-checkbox-circle-line"></i>
        </div>
        <div class="stat-content">
          <span class="stat-value">{{ statsActive }}</span>
          <span class="stat-label">Active</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon inactive">
          <i class="ri-close-circle-line"></i>
        </div>
        <div class="stat-content">
          <span class="stat-value">{{ statsInactive }}</span>
          <span class="stat-label">Inactive</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon development">
          <i class="ri-tools-line"></i>
        </div>
        <div class="stat-content">
          <span class="stat-value">{{ statsDevelopment }}</span>
          <span class="stat-label">Development</span>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="content-card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Merchant Name</th>
              <th>Company</th>
              <th>Email</th>
              <th>Status</th>
              <th>Country</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody v-if="loading">
            <tr>
              <td colspan="6" class="loading-cell">
                <div class="loading-spinner">
                  <i class="ri-loader-4-line spin"></i>
                  <span>Loading merchants...</span>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="merchants.length">
            <tr v-for="merchant in merchants" :key="merchant.merchant_uid || merchant.id">
              <td>
                <span class="merchant-name">{{ merchant.merchant_name || '—' }}</span>
              </td>
              <td>{{ (merchant.user && merchant.user.company_name) || '—' }}</td>
              <td>{{ merchant.merchant_email || (merchant.user && merchant.user.email) || '—' }}</td>
              <td>
                <span :class="['status-badge', statusClass(merchant.merchant_status)]">
                  {{ merchant.merchant_status || '—' }}
                </span>
              </td>
              <td>{{ merchant.merchant_country || '—' }}</td>
              <td>{{ formatDate(merchant.created_at) }}</td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="empty-cell">
                <div class="empty-state">
                  <i class="ri-inbox-line empty-icon"></i>
                  <p>No merchants found</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="pagination-container">
        <div class="pagination-info">
          Showing {{ startIndex }} to {{ endIndex }} of {{ totalMerchants }} entries
        </div>
        <div class="pagination">
          <button
            class="pagination-btn"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <i class="ri-arrow-left-s-line"></i> Previous
          </button>
          <button
            v-for="p in visiblePages"
            :key="p"
            :class="['pagination-btn', 'page-num', { active: currentPage === p }]"
            @click="goToPage(p)"
          >
            {{ p }}
          </button>
          <button
            class="pagination-btn"
            :disabled="currentPage >= lastPage"
            @click="goToPage(currentPage + 1)"
          >
            Next <i class="ri-arrow-right-s-line"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'SuperMerchants',
  data() {
    return {
      merchants: [],
      loading: false,
      search: '',
      statusFilter: '',
      debounceTimer: null,
      currentPage: 1,
      perPage: 10,
      totalMerchants: 0,
      lastPage: 1
    };
  },
  computed: {
    statsActive() {
      return this.merchants.filter((m) => (m.merchant_status || '').toUpperCase() === 'ACTIVE').length;
    },
    statsInactive() {
      return this.merchants.filter((m) => (m.merchant_status || '').toUpperCase() === 'INACTIVE').length;
    },
    statsDevelopment() {
      return this.merchants.filter((m) => (m.merchant_status || '').toUpperCase() === 'DEVELOPMENT').length;
    },
    startIndex() {
      if (this.totalMerchants === 0) return 0;
      return (this.currentPage - 1) * this.perPage + 1;
    },
    endIndex() {
      return Math.min(this.startIndex + this.perPage - 1, this.totalMerchants);
    },
    visiblePages() {
      const pages = [];
      let start = Math.max(1, this.currentPage - 2);
      let end = Math.min(this.lastPage, start + 4);
      if (end - start < 4) start = Math.max(1, end - 4);
      for (let i = start; i <= end; i++) pages.push(i);
      return pages;
    }
  },
  mounted() {
    this.fetchMerchants();
  },
  methods: {
    handleSearch() {
      if (this.debounceTimer) clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.currentPage = 1;
        this.fetchMerchants();
      }, 400);
    },
    clearSearch() {
      this.search = '';
      this.currentPage = 1;
      this.fetchMerchants();
    },
    statusClass(status) {
      const s = (status || '').toUpperCase();
      if (s === 'ACTIVE') return 'active';
      if (s === 'INACTIVE') return 'inactive';
      if (s === 'DEVELOPMENT') return 'development';
      return '';
    },
    formatDate(date) {
      if (!date) return '—';
      const d = new Date(date);
      return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    goToPage(page) {
      if (page < 1 || page > this.lastPage) return;
      this.currentPage = page;
      this.fetchMerchants();
    },
    async fetchMerchants() {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/v1/super/merchants', {
          params: {
            search: this.search || undefined,
            status: this.statusFilter || undefined,
            per_page: this.perPage,
            page: this.currentPage
          }
        });
        if (data.success && data.data) {
          this.merchants = data.data.data || [];
          this.currentPage = data.data.current_page || 1;
          this.totalMerchants = data.data.total || 0;
          this.lastPage = data.data.last_page || 1;
        } else {
          this.merchants = [];
        }
      } catch (err) {
        console.error('Failed to load merchants:', err);
        this.$swal.fire('Error!', err.response?.data?.message || 'Failed to load merchants', 'error');
        this.merchants = [];
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
.super-merchants {
  animation: fadeIn 0.5s ease;
}

.page-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  color: #010647;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-header h1 i {
  color: #f59e0b;
}

.header-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.search-box {
  display: flex;
  align-items: center;
  background: #f8f9fc;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  min-width: 220px;
  transition: border-color 0.2s;
}

.search-box:focus-within {
  border-color: #010647;
}

.search-box i.ri-search-line {
  color: #64748b;
  margin-right: 0.5rem;
}

.search-box input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #0f172a;
  font-size: 0.9rem;
}

.search-box input::placeholder {
  color: #94a3b8;
}

.search-clear {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.25rem;
  display: flex;
  align-items: center;
}

.search-clear:hover {
  color: #ff4757;
}

.status-filter {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem 1rem;
  color: #0f172a;
  font-size: 0.9rem;
  cursor: pointer;
}

.status-filter:focus {
  outline: none;
  border-color: #010647;
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.stat-icon.total {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}

.stat-icon.active {
  background: rgba(0, 184, 148, 0.1);
  color: #00b894;
}

.stat-icon.inactive {
  background: rgba(255, 71, 87, 0.1);
  color: #ff4757;
}

.stat-icon.development {
  background: rgba(253, 203, 110, 0.15);
  color: #e6a817;
}

.stat-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #010647;
}

.stat-label {
  font-size: 0.8rem;
  color: #666;
}

/* Table */
.content-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  overflow: hidden;
  padding: 1.5rem;
}

.table-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

thead {
  background: #f8f9fc;
}

th {
  padding: 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #010647;
  border-bottom: 2px solid #f1f1f1;
  background: #f8f9fc;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #f1f1f1;
  color: #334155;
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

tbody tr:hover td {
  background: #f8f9fc;
}

.merchant-name {
  font-weight: 500;
  color: #010647;
}

/* Status badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 500;
  gap: 0.5rem;
}

.status-badge::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.status-badge.active {
  background: rgba(0, 184, 148, 0.1);
  color: #00b894;
}

.status-badge.active::before {
  background: #00b894;
}

.status-badge.inactive {
  background: rgba(255, 71, 87, 0.1);
  color: #ff4757;
}

.status-badge.inactive::before {
  background: #ff4757;
}

.status-badge.development {
  background: rgba(253, 203, 110, 0.15);
  color: #e6a817;
}

.status-badge.development::before {
  background: #e6a817;
}

/* Loading & Empty */
.loading-cell,
.empty-cell {
  padding: 3rem !important;
  text-align: center;
}

.loading-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #666;
}

.loading-spinner .spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  color: #999;
}

.empty-icon {
  font-size: 3rem;
  color: #ccc;
}

.empty-state p {
  margin: 0;
  font-size: 1rem;
}

/* Pagination */
.pagination-container {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding-top: 1.5rem;
  margin-top: 1.5rem;
  border-top: 2px solid #f1f1f1;
}

.pagination-info {
  font-size: 0.875rem;
  color: #666;
}

.pagination {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-btn {
  background: #f8f9fc;
  border: none;
  color: #334155;
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  font-size: 0.875rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
  background: #e2e8f0;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-btn.active {
  background: #010647;
  color: white;
}

.pagination-btn.page-num {
  min-width: 36px;
  justify-content: center;
}

/* Responsive */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    flex-direction: column;
  }

  .search-box {
    min-width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  th,
  td {
    padding: 0.75rem;
    font-size: 0.8rem;
  }

  .pagination-container {
    flex-direction: column;
    align-items: stretch;
  }

  .pagination {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .table-container {
    font-size: 0.8rem;
  }
}
</style>
