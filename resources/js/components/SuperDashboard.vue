<template>
  <div class="super-container">
    <div class="sidebar">
      <div class="sidebar-header">
        <img src="/assets/logo.png" alt="Logo" class="sidebar-logo"/>
        <span class="sidebar-badge">SUPER</span>
      </div>
      <nav class="sidebar-nav">
        <router-link :to="{ name: 'super-dashboard' }" class="nav-item" exact-active-class="active">
          <i class="ri-dashboard-line"></i> Dashboard
        </router-link>
        <router-link :to="{ name: 'super-merchants' }" class="nav-item" active-class="active">
          <i class="ri-store-2-line"></i> Merchants
        </router-link>
        <router-link :to="{ name: 'super-charges' }" class="nav-item" active-class="active">
          <i class="ri-money-cny-circle-line"></i> Charges
        </router-link>
        <router-link :to="{ name: 'super-users' }" class="nav-item" active-class="active">
          <i class="ri-shield-user-line"></i> Users
        </router-link>
        <router-link :to="{ name: 'super-transactions' }" class="nav-item" active-class="active">
          <i class="ri-exchange-dollar-line"></i> Transactions
        </router-link>
        <button @click="handleLogout" class="nav-item logout">
          <i class="ri-logout-box-line"></i> Logout
        </button>
      </nav>
    </div>
    <div class="main-content">
      <router-view></router-view>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'SuperDashboard',
  methods: {
    async handleLogout() {
      localStorage.removeItem('authToken');
      localStorage.removeItem('user');
      localStorage.removeItem('refreshToken');
      delete axios.defaults.headers.common['Authorization'];
      this.$swal.fire({ title: 'Logged out', icon: 'success', timer: 1200, showConfirmButton: false });
      setTimeout(() => this.$router.push('/login'), 1200);
    }
  }
};
</script>

<style scoped>
.super-container {
  display: flex;
  min-height: 100vh;
  background: #f0f2f5;
}

.sidebar {
  width: 260px;
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
  color: white;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.sidebar-logo { width: 110px; height: auto; }

.sidebar-badge {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #0f172a;
  font-size: 0.6rem;
  font-weight: 800;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  letter-spacing: 1px;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.7rem 1rem;
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  border-radius: 8px;
  transition: all 0.2s;
  font-size: 0.9rem;
  font-weight: 500;
}

.nav-item:hover, .nav-item.active {
  background: rgba(255,255,255,0.08);
  color: white;
}

.nav-item i { font-size: 1.2rem; }

.logout {
  margin-top: auto;
  border: none;
  background: none;
  cursor: pointer;
  color: #f87171;
}

.logout:hover { background: rgba(248,113,113,0.1); }

.main-content {
  flex: 1;
  padding: 1.5rem 2rem;
  overflow-y: auto;
}
</style>
