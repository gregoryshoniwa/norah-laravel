<!-- This will contain the current dashboard content -->
<template>
  <div class="dashboard-content">
    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-info">
          <h3>Total Volume</h3>
          <p class="amount">{{ formatAmount(stats.totalVolume) }}</p>
        </div>
        <div class="stat-icon">
          <i class="ri-money-dollar-circle-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3># Transactions</h3>
          <p class="amount">{{ stats.totalTransactions }}</p>
        </div>
        <div class="stat-icon">
          <i class="ri-exchange-funds-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3>System Charges</h3>
          <p class="amount">{{ formatAmount(stats.systemCharges) }}</p>
        </div>
        <div class="stat-icon">
          <i class="ri-line-chart-line"></i>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <h3>Profit / Charges</h3>
          <p class="amount">{{ formatAmount(stats.merchantCharges) }}</p>
        </div>
        <div class="stat-icon">
          <i class="ri-user-line"></i>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="transactions-section">
      <div class="section-header">
        <h2>Recent Transactions</h2>
        <button class="btn-view-all">View All</button>
      </div>
      <div class="transactions-table">
        <table>
          <thead>
            <tr>
              <th>Tran ID</th>
              <th>Total</th>
              <th>Amount</th>
              <th>Charge</th>
              <th>Type</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody v-if="!loading">
            <tr v-for="transaction in transactions" :key="transaction.id">
              <td>#{{ transaction.id }}</td>
              <td>{{ formatAmount(Number(transaction.amount) + Number(transaction.charge)) }}</td>
              <td>{{ formatAmount(transaction.amount) }}</td>
              <td>{{ formatAmount(transaction.charge) }}</td>
              <td>{{ transaction.type }}</td>
              <td>
                <span :class="['status',
                  transaction.status === 'COMPLETED' ? 'success' :
                  transaction.status === 'PENDING' ? 'pending' : 'failed'
                ]">
                  {{ transaction.status }}
                </span>
              </td>
              <td>{{ new Date(transaction.created_at).toLocaleString() }}</td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="text-center">Loading...</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button
          :disabled="currentPage === 1"
          @click="handlePageChange(currentPage - 1)"
          class="pagination-btn"
        >
          Previous
        </button>
        <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button
          :disabled="currentPage === totalPages"
          @click="handlePageChange(currentPage + 1)"
          class="pagination-btn"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'DashboardHome',
  inject: ['selectedCurrency', 'currencies'],
  data() {
    return {
      stats: {
        totalVolume: 0,
        totalTransactions: 0,
        systemCharges: 0,
        merchantCharges: 0
      },
      transactions: [],
      search: '',
      currentPage: 1,
      totalPages: 0,
      loading: false,
      debounceTimer: null
    };
  },
  mounted() {
    this.loadDashboardStats();
    this.loadTransactions();
  },
  methods: {
    formatAmount(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: this.selectedCurrency
      }).format(amount);
    },

    async loadDashboardStats() {
      try {
        const response = await axios.get('/api/v1/dashboard/stats', {
          params: {
            currency: this.selectedCurrency
          }
        });
        if (response.data.success) {
          this.stats = response.data.data;
        }
      } catch (error) {
        console.error('Error loading dashboard stats:', error);
        this.$swal.fire('Error!', 'Failed to load dashboard statistics', 'error');
      }
    },

    async loadTransactions() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/dashboard/recent-transactions', {
          params: {
            search: this.search,
            page: this.currentPage,
            per_page: 5,
            currency: this.selectedCurrency
          }
        });
        if (response.data.success) {
          this.transactions = response.data.data.data;
          this.currentPage = response.data.data.current_page;
          this.totalPages = response.data.data.last_page;
        }
      } catch (error) {
        console.error('Error loading transactions:', error);
        this.$swal.fire('Error!', 'Failed to load transactions', 'error');
      } finally {
        this.loading = false;
      }
    },

    handleSearch() {
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
      }
      this.debounceTimer = setTimeout(() => {
        this.currentPage = 1;
        this.loadTransactions();
      }, 500);
    },

    handlePageChange(page) {
      this.currentPage = page;
      this.loadTransactions();
    }
  }
};
</script>

<style scoped>
/* Dashboard Styles */
.dashboard-content {
  animation: fadeIn 0.5s ease;
}

/* Stats Grid Styles */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 8px 16px rgba(0,0,0,0.05);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  transition: all 0.3s ease;
  animation: slideIn 0.5s ease;
  animation-fill-mode: both;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 20px rgba(0,0,0,0.1);
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
  margin: 0 0 0.5rem 0;
  color: #010647;
  transition: all 0.3s ease;
}

.stat-card:hover .amount {
  color: #020968;
  transform: scale(1.05);
}

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
  transition: all 0.3s ease;
  box-shadow: 0 4px 8px rgba(1,6,71,0.2);
}

.stat-card:hover .stat-icon {
  transform: rotate(15deg);
}

/* Transactions Section Styles */
.transactions-section {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 8px 16px rgba(0,0,0,0.05);
  animation: slideUp 0.5s ease;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f1f1;
}

.section-header h2 {
  margin: 0;
  font-size: 1.25rem;
  color: #010647;
  font-weight: 600;
}

.btn-view-all {
  background: linear-gradient(135deg, #010647 0%, #020968 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 500;
  box-shadow: 0 4px 8px rgba(1,6,71,0.2);
}

.btn-view-all:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(1,6,71,0.3);
}

/* Table Styles */
.transactions-table {
  overflow-x: auto;
  margin: 0 -1.5rem;
  padding: 0 1.5rem;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-bottom: 1rem;
}

th {
  text-align: left;
  padding: 1rem;
  color: #666;
  font-weight: 600;
  background: #f8f9fc;
  border-bottom: 2px solid #f1f1f1;
  position: sticky;
  top: 0;
  z-index: 10;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #f1f1f1;
  transition: background 0.3s ease;
}

tr:hover td {
  background: #f8f9fc;
}

.status {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.status::before {
  content: '';
  display: block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status.success {
  background: rgba(0,184,148,0.1);
  color: #00b894;
}

.status.success::before {
  background: #00b894;
}

.status.pending {
  background: rgba(253,203,110,0.1);
  color: #fdcb6e;
}

.status.pending::before {
  background: #fdcb6e;
}

.status.failed {
  background: rgba(255,71,87,0.1);
  color: #ff4757;
}

.status.failed::before {
  background: #ff4757;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid #f1f1f1;
}

.pagination-btn {
  background: linear-gradient(135deg, #010647 0%, #020968 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 500;
  box-shadow: 0 4px 8px rgba(1,6,71,0.2);
}

.pagination-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(1,6,71,0.3);
}

.pagination-btn:disabled {
  background: #cccccc;
  cursor: not-allowed;
  box-shadow: none;
}

.page-info {
  color: #666;
  font-size: 0.875rem;
  font-weight: 500;
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideIn {
  from {
    transform: translateX(-20px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* Apply staggered animation to stat cards */
.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
</style>
