<template>
  <div class="super-dashboard-home">
    <!-- Page Header -->
    <header class="page-header">
      <div>
        <h1>Norah Payment Gateway</h1>
        <p class="subtitle">Super Admin Dashboard</p>
      </div>
      <div class="currency-selector">
        <label for="super-dashboard-currency">Currency</label>
        <select id="super-dashboard-currency" v-model="selectedCurrency" @change="handleCurrencyChange">
          <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
        </select>
      </div>
    </header>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card" v-for="(card, idx) in statCards" :key="card.key" :style="{ animationDelay: `${idx * 0.05}s` }">
        <div class="stat-info">
          <h3>{{ card.label }}</h3>
          <p class="stat-value" :class="card.valueClass">{{ card.displayValue }}</p>
        </div>
        <div class="stat-icon" :class="card.iconClass">
          <i :class="card.icon"></i>
        </div>
      </div>
    </div>

    <!-- Companies Table -->
    <section class="section companies-section">
      <div class="section-header">
        <h2><i class="ri-building-line"></i> Companies</h2>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Company Name</th>
              <th>Admins</th>
              <th>Merchants</th>
              <th>Transactions</th>
              <th>Volume</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody v-if="!loadingCompanies">
            <tr v-for="company in companies" :key="company.company_name">
              <td>{{ company.company_name }}</td>
              <td>{{ company.admin_count }}</td>
              <td>{{ company.merchant_count }}</td>
              <td>{{ company.transaction_count }}</td>
              <td>{{ formatUsd(company.volume) }}</td>
              <td>{{ formatDate(company.created_at) }}</td>
            </tr>
            <tr v-if="companies.length === 0">
              <td colspan="6" class="empty-cell">No companies yet</td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="loading-cell">
                <i class="ri-loader-4-line spin"></i> Loading companies...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Recent Transactions Table -->
    <section class="section transactions-section">
      <div class="section-header">
        <h2><i class="ri-exchange-dollar-line"></i> Recent Transactions</h2>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>To Customer</th>
              <th>Our Charge</th>
              <th>Method</th>
              <th>Status</th>
              <th>User</th>
            </tr>
          </thead>
          <tbody v-if="!loadingStats">
            <tr v-for="txn in recentTransactions" :key="txn.id">
              <td>#{{ txn.id }}</td>
              <td>{{ formatDate(txn.created_at) }}</td>
              <td>{{ formatUsd(getTransactionAmount(txn)) }}</td>
              <td>{{ formatUsd(txn.charge ?? 0) }}</td>
              <td>{{ txn.payment_method || '—' }}</td>
              <td>
                <span :class="['status-badge', statusClass(txn.status)]">{{ txn.status }}</span>
              </td>
              <td>{{ getUserDisplay(txn) }}</td>
            </tr>
            <tr v-if="recentTransactions.length === 0">
              <td colspan="7" class="empty-cell">No recent transactions</td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="7" class="loading-cell">
                <i class="ri-loader-4-line spin"></i> Loading transactions...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'SuperDashboardHome',

  data() {
    return {
      stats: {
        companies: 0,
        totalMerchants: 0,
        activeMerchants: 0,
        totalTransactions: 0,
        totalVolume: 0,
        totalProfit: 0,
        completed: 0,
        pending: 0,
        failed: 0,
        superUsers: 0,
        recentTransactions: []
      },
      companies: [],
      loadingStats: true,
      loadingCompanies: true,
      selectedCurrency: 'USD',
      currencies: ['USD', 'ZWG', 'ZAR', 'BWP', 'EUR', 'GBP']
    };
  },

  computed: {
    statCards() {
      const s = this.stats;
      return [
        { key: 'companies', label: 'Companies', displayValue: s.companies, icon: 'ri-building-line', iconClass: 'default' },
        { key: 'merchants', label: 'Total Merchants', displayValue: `${s.totalMerchants} / ${s.activeMerchants} active`, icon: 'ri-store-2-line', iconClass: 'default' },
        { key: 'transactions', label: 'Total Transactions', displayValue: s.totalTransactions, icon: 'ri-exchange-dollar-line', iconClass: 'default' },
        { key: 'volume', label: 'Volume (to Customer)', displayValue: this.formatUsd(s.totalVolume), icon: 'ri-money-dollar-circle-line', iconClass: 'default' },
        { key: 'profit', label: 'Total Profit (Charges)', displayValue: this.formatUsd(s.totalProfit), icon: 'ri-line-chart-line', iconClass: 'accent' },
        { key: 'completed', label: 'Completed', displayValue: s.completed, icon: 'ri-checkbox-circle-line', iconClass: 'success' },
        { key: 'pending', label: 'Pending', displayValue: s.pending, icon: 'ri-time-line', iconClass: 'pending' },
        { key: 'failed', label: 'Failed', displayValue: s.failed, icon: 'ri-close-circle-line', iconClass: 'failed' },
        { key: 'superUsers', label: 'Super Users', displayValue: s.superUsers, icon: 'ri-shield-user-line', iconClass: 'default' }
      ];
    },
    recentTransactions() {
      const list = this.stats.recentTransactions || [];
      return list.slice(0, 10);
    }
  },

  mounted() {
    this.setAuthHeader();
    this.loadStats();
    this.loadCompanies();
  },

  methods: {
    setAuthHeader() {
      const token = localStorage.getItem('authToken');
      if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      } else {
        this.$router.push('/login');
      }
    },

    formatUsd(amount, currency = null) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || this.selectedCurrency
      }).format(amount ?? 0);
    },

    formatDate(date) {
      if (!date) return '—';
      return new Date(date).toLocaleString();
    },

    getTransactionAmount(txn) {
      return txn.numeric_amount ?? txn.amount ?? 0;
    },

    getUserDisplay(txn) {
      if (!txn.user) return txn.user_name || '—';
      return txn.user.email || txn.user.company_name || txn.user_name || '—';
    },

    statusClass(status) {
      const s = (status || '').toUpperCase();
      if (s === 'COMPLETED') return 'success';
      if (s === 'PENDING') return 'pending';
      if (s === 'FAILED') return 'failed';
      return '';
    },

    handleCurrencyChange() {
      this.loadStats();
      this.loadCompanies();
    },

    async loadStats() {
      this.loadingStats = true;
      try {
        const res = await axios.get('/api/v1/super/dashboard/stats', {
          params: { currency: this.selectedCurrency }
        });
        if (res.data.success) {
          this.stats = { ...this.stats, ...res.data.data };
        }
      } catch (err) {
        console.error('Error loading dashboard stats:', err);
        this.$swal.fire('Error', 'Failed to load dashboard statistics', 'error');
      } finally {
        this.loadingStats = false;
      }
    },

    async loadCompanies() {
      this.loadingCompanies = true;
      try {
        const res = await axios.get('/api/v1/super/companies', {
          params: { currency: this.selectedCurrency }
        });
        if (res.data.success) {
          this.companies = res.data.data || [];
        }
      } catch (err) {
        console.error('Error loading companies:', err);
        this.$swal.fire('Error', 'Failed to load companies', 'error');
      } finally {
        this.loadingCompanies = false;
      }
    }
  }
};
</script>

<style scoped>
.super-dashboard-home {
  animation: fadeIn 0.5s ease;
  min-height: 100%;
}

/* Page Header */
.page-header {
  margin-bottom: 2rem;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.page-header h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 0.25rem 0;
}

.page-header .subtitle {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0;
}

.currency-selector {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 130px;
}

.currency-selector label {
  font-size: 0.7rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.currency-selector select {
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem 0.65rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #0f172a;
  background: #fff;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
  border: 1px solid rgba(15, 23, 42, 0.06);
  animation: slideIn 0.4s ease both;
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
}

.stat-info h3 {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 0.5rem 0;
}

.stat-value {
  font-size: 1.35rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
}

.stat-icon.default {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  color: #f8fafc;
}

.stat-icon.accent {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #0f172a;
}

.stat-icon.success {
  background: rgba(34, 197, 94, 0.15);
  color: #16a34a;
}

.stat-icon.pending {
  background: rgba(245, 158, 11, 0.2);
  color: #d97706;
}

.stat-icon.failed {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

/* Sections */
.section {
  background: #fff;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
  border: 1px solid rgba(15, 23, 42, 0.06);
}

.section-header {
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.section-header h2 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-header h2 i {
  color: #f59e0b;
}

/* Tables */
.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.data-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-weight: 600;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}

.data-table tbody tr:hover td {
  background: #f8fafc;
}

.empty-cell,
.loading-cell {
  text-align: center;
  color: #94a3b8;
  padding: 2rem !important;
}

.loading-cell i {
  margin-right: 0.5rem;
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Status Badges */
.status-badge {
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge.success {
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

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive */
@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .page-header {
    align-items: stretch;
    flex-direction: column;
  }

  .stats-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .section {
    padding: 1rem;
  }

  .data-table {
    font-size: 0.8rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.5rem 0.75rem;
  }

  .page-header h1 {
    font-size: 1.4rem;
  }
}
</style>
