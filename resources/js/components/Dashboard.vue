<template>
  <div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="sidebar-header">
        <img src="/assets/logo.png" alt="Logo" class="sidebar-logo"/>
        <!-- <h3 class="sidebar-title">NORAH</h3> -->
      </div>
      <nav class="sidebar-nav">
        <router-link :to="{ name: 'dashboard' }" exact class="nav-item" active-class="active">
          <i class="ri-dashboard-line"></i>
          Dashboard
        </router-link>
        <router-link :to="{ name: 'merchants' }" class="nav-item" active-class="active">
          <i class="ri-store-2-line"></i>
          Merchants
        </router-link>
        <router-link :to="{ name: 'users' }" class="nav-item" active-class="active">
          <i class="ri-user-settings-line"></i>
          Users
        </router-link>
        <router-link :to="{ name: 'transactions' }" class="nav-item" active-class="active">
          <i class="ri-exchange-dollar-line"></i>
          Transactions
        </router-link>
        <router-link :to="{ name: 'settings' }" class="nav-item" active-class="active">
          <i class="ri-settings-3-line"></i>
          Settings
        </router-link>
        <button @click="handleLogout" class="nav-item logout">
          <i class="ri-logout-box-line"></i>
          Logout
        </button>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">

      <!-- Main Content Area -->

        <router-view></router-view>

    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Dashboard',
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
      debounceTimer: null,
      currencies: [],
      selectedCurrency: 'USD'
    };
  },
  provide() {
    return {
      selectedCurrency: this.selectedCurrency,
      currencies: this.currencies,
    }
  },
  mounted() {

  },
  methods: {
    formatAmount(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: this.selectedCurrency
      }).format(amount);
    },

    handleCurrencyChange() {
      // Reload all data with new currency
      this.loadDashboardStats();
      this.loadTransactions();
    },

    async loadDashboardStats() {
      try {
        const token = localStorage.getItem('authToken');
        if (!token) {
          this.$router.push('/login');
          return;
        }

        const response = await axios.get('/api/v1/dashboard/stats', {
          headers: {
            'Authorization': `Bearer ${token}`
          },
          params: {
            currency: this.selectedCurrency
          }
        });
        if (response.data.success) {
          this.stats = response.data.data;
          this.currencies = response.data.data.currencies;
        }
      } catch (error) {
        console.error('Error loading dashboard stats:', error);
        this.$swal.fire('Error!', 'Failed to load dashboard statistics', 'error');
      }
    },

    async loadTransactions() {
      try {
        const token = localStorage.getItem('authToken');
        if (!token) {
          this.$router.push('/login');
          return;
        }

        const response = await axios.get('/api/v1/dashboard/recent-transactions', {
          headers: {
            'Authorization': `Bearer ${token}`
          },
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
      }
    },

    handleSearch() {
      // Clear existing timer
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
      }

      // Set new timer
      this.debounceTimer = setTimeout(() => {
        this.currentPage = 1; // Reset to first page
        this.loadTransactions();
      }, 500);
    },

    handlePageChange(page) {
      this.currentPage = page;
      this.loadTransactions();
    },

    async handleLogout() {
      try {
        // Clear all localStorage data
        localStorage.removeItem('authToken');
        localStorage.removeItem('user');
        localStorage.removeItem('refreshToken');

        // Remove Authorization header
        delete axios.defaults.headers.common['Authorization'];

        // Show success message
        this.$swal.fire({
          title: 'Success!',
          text: 'You have been logged out successfully',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });

        // Redirect to login page
        setTimeout(() => {
          this.$router.push('/login');
        }, 1500);
      } catch (error) {
        console.error('Logout error:', error);
        this.$swal.fire('Error!', 'Failed to logout properly', 'error');
      }
    }
  }
};
</script>

<style scoped>
.dashboard-container {
  display: flex;
  min-height: 100vh;
  background: #f8f9fc;
}

/* Sidebar Styles */
.sidebar {
  width: 260px;
  background: #010647;
  color: white;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  display: flex;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-logo {
  width: 125px;
  height: auto;
  margin-right: 1rem;
}

.sidebar-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  color: rgba(255,255,255,0.7);
  text-decoration: none;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.nav-item:hover, .nav-item.active {
  background: rgba(255,255,255,0.1);
  color: white;
}

.nav-item i {
  font-size: 1.25rem;
}

.logout {
  margin-top: auto;
  border: none;
  background: none;
  cursor: pointer;
  color: #ff4757;
}

.logout:hover {
  background: rgba(255,71,87,0.1);
}

/* Main Content Styles */
.main-content {
  flex: 1;
  padding: 1.5rem 2rem;
  overflow-y: auto;
  background: #f8f9fc;
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
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.stat-info h3 {
  color: #666;
  font-size: 0.875rem;
  margin: 0 0 0.5rem 0;
}

.amount {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0 0 0.5rem 0;
  color: #010647;
}

.trend {
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.trend.positive {
  color: #00b894;
}

.trend.negative {
  color: #ff4757;
}

.stat-icon {
  background: #010647;
  color: white;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

/* Transactions Section Styles */
.transactions-section {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h2 {
  margin: 0;
  font-size: 1.25rem;
}

.btn-view-all {
  background: #010647;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.3s ease;
}

.btn-view-all:hover {
  background: #020968;
}

/* Table Styles */
.transactions-table {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  text-align: left;
  padding: 1rem;
  border-bottom: 2px solid #f1f1f1;
  color: #666;
  font-weight: 600;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #f1f1f1;
}

.status {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.875rem;
}

.status.success {
  background: rgba(0,184,148,0.1);
  color: #00b894;
}

.status.pending {
  background: rgba(253,203,110,0.1);
  color: #fdcb6e;
}

.status.failed {
  background: rgba(255,71,87,0.1);
  color: #ff4757;
}

.text-center {
  text-align: center;
}

/* Main Content Area */
.main-content-area {
  animation: fadeIn 0.3s ease;
  min-height: calc(100vh - 140px);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Pagination Styles */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 1.5rem;
}

.pagination-btn {
  background: #010647;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.3s ease;
}

.pagination-btn:hover:not(:disabled) {
  background: #020968;
}

.pagination-btn:disabled {
  background: #cccccc;
  cursor: not-allowed;
}

.page-info {
  color: #666;
  font-size: 0.875rem;
}
</style>
