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
        <button class="btn-primary" @click="applyFilters">
          <i class="ri-filter-3-line"></i>
          Apply Filters
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-info">
          <h3>Total Volume</h3>
          <p class="amount">{{ formatAmount(stats.totalVolume) }}</p>
          <span class="trend positive">
            <i class="ri-arrow-up-line"></i>
            12.5%
          </span>
        </div>
        <div class="stat-icon">
          <i class="ri-money-dollar-circle-line"></i>
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
            placeholder="Search transactions..."
          >
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
              <th>Amount</th>
              <th>Type</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading">
            <tr v-for="transaction in transactions" :key="transaction.id">
              <td>#{{ transaction.id }}</td>
              <td>{{ formatDate(transaction.created_at) }}</td>
              <td>{{ formatAmount(transaction.amount) }}</td>
              <td>{{ transaction.type }}</td>
              <td>
                <span :class="['status-badge', transaction.status.toLowerCase()]">
                  {{ transaction.status }}
                </span>
              </td>
              <td class="actions">
                <button class="btn-icon mr-2" @click="viewDetails(transaction)" title="View Details">
                  <i class="ri-eye-line"></i>
                </button>
                <button class="btn-icon ml-2" @click="downloadReceipt(transaction)" title="Download Receipt">
                  <i class="ri-file-list-3-line"></i>
                </button>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="text-center">
                <div class="loading-spinner">
                  <i class="ri-loader-4-line spin"></i>
                  Loading...
                </div>
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
    <div class="modal" v-if="selectedTransaction">
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
              <label>Amount</label>
              <span>{{ formatAmount(selectedTransaction.amount) }}</span>
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
      filters: {
        startDate: '',
        endDate: '',
        status: ''
      },
      stats: {
        totalVolume: 0,
        completed: 0,
        pending: 0,
        failed: 0
      },
      transactions: [],
      selectedTransaction: null,
      currentPage: 1,
      itemsPerPage: 10,
      totalItems: 0,
      currency: 'USD'
    }
  },
  computed: {
    totalPages() {
      return Math.ceil(this.totalItems / this.itemsPerPage);
    },
    startIndex() {
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
    applyFilters() {
      // Implementation for applying filters
      this.loadTransactions();
    },
    async loadTransactions() {
      this.loading = true;
      try {
        // Get transactions from API
        const response = await axios.get('/api/v1/transactions/all', {
          params: {
            search: this.search,
            page: this.currentPage,
            per_page: this.itemsPerPage,
            status: this.filters.status,
            start_date: this.filters.startDate,
            end_date: this.filters.endDate,
            currency: this.currency
          }
        });

        if (response.data.success) {
          this.transactions = response.data.data.data;
          this.currentPage = response.data.data.current_page;
          this.totalItems = response.data.data.total;

          // Load transaction stats
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
          params: {
            currency: this.currency
          }
        });

        if (response.data.success) {
          this.stats.totalVolume = response.data.data.totalVolume;

          // Count transactions by status
          const statusResponse = await axios.get('/api/v1/transactions/all', {
            params: {
              count_by_status: true,
              currency: this.currency
            }
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
        // Fetch detailed transaction information
        const response = await axios.get(`/api/v1/transactions/details/${transaction.id}`);

        if (response.data.success) {
          const transactionDetails = response.data.data;

          // Generate timeline events based on transaction data
          const timeline = this.generateTimeline(transactionDetails);

          // Set the selected transaction with timeline
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

        // Fallback to basic details if API fails
        this.selectedTransaction = {
          ...transaction,
          timeline: this.generateTimeline(transaction),
          customer_name: transaction.user_name || 'N/A'
        };
      }
    },

    generateTimeline(transaction) {
      const timeline = [];

      // Add transaction creation event
      timeline.push({
        title: 'Transaction Initiated',
        description: `${transaction.type} transaction started`,
        timestamp: transaction.created_at,
        icon: 'ri-play-circle-line'
      });

      // Add status update events based on transaction status
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

    downloadReceipt(transaction) {
      try {
        // Create receipt data
        const receiptData = {
          transaction_id: transaction.id,
          date: this.formatDate(transaction.created_at),
          amount: this.formatAmount(transaction.amount),
          status: transaction.status,
          type: transaction.type,
          customer: transaction.user_name || 'N/A',
          reference: transaction.reference || 'N/A'
        };

        // Generate PDF receipt
        axios.post('/api/v1/transactions/receipt', receiptData, { responseType: 'blob' })
          .then(response => {
            // Create blob link to download
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
        // Show loading message
        this.$swal.fire({
          title: 'Exporting...',
          text: 'Please wait while we generate your export file',
          allowOutsideClick: false,
          didOpen: () => {
            this.$swal.showLoading();
          }
        });

        // Get export parameters
        const params = {
          format: 'csv', // or 'xlsx'
          status: this.filters.status,
          start_date: this.filters.startDate,
          end_date: this.filters.endDate,
          currency: this.currency
        };

        // Request export file
        axios.get('/api/v1/transactions/export', {
          params: params,
          responseType: 'blob'
        })
        .then(response => {
          // Create blob link to download
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement('a');
          link.href = url;

          // Get filename from response headers or use default
          const contentDisposition = response.headers['content-disposition'];
          let filename = 'transactions-export.csv';

          if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="(.+)"/);
            if (filenameMatch.length === 2) {
              filename = filenameMatch[1];
            }
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
    this.loadTransactions();
  }
}
</script>

<style scoped>
.transactions-container {
  animation: fadeIn 0.5s ease;
}

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
  gap: 1rem;
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
}

/* Stats Grid */
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

/* Table Styles */
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
}

.search-box {
  display: flex;
  align-items: center;
  background: #f8f9fc;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  width: 300px;
  border: 2px solid #e2e8f0;
}

.search-box input {
  border: none;
  outline: none;
  background: transparent;
  margin-left: 0.5rem;
  width: 100%;
}

.table-container {
  overflow-x: auto;
}

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
}

tbody tr {
  transition: transform 0.3s ease;
}

tbody tr:hover {
  background: #f8f9fc;
  transform: translateX(5px);
}

.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.status-badge::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.status-badge.completed {
  background: rgba(0,184,148,0.1);
  color: #00b894;
}

.status-badge.completed::before {
  background: #00b894;
}

.status-badge.pending {
  background: rgba(253,203,110,0.1);
  color: #fdcb6e;
}

.status-badge.pending::before {
  background: #fdcb6e;
}

.status-badge.failed {
  background: rgba(255,71,87,0.1);
  color: #ff4757;
}

.status-badge.failed::before {
  background: #ff4757;
}

/* Pagination Styles */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f1f1;
}

.pagination {
  display: flex;
  gap: 0.5rem;
}

.pagination-btn {
  background: #f8f9fc;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.pagination-btn:hover:not(:disabled) {
  background: #e2e8f0;
}

.pagination-btn.active {
  background: #010647;
  color: white;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal Styles */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  animation: scaleIn 0.3s ease;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 2px solid #f1f1f1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  background: white;
  z-index: 1;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1.5rem;
  border-top: 2px solid #f1f1f1;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

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

/* Timeline Styles */
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

.timeline-content p {
  margin: 0;
  color: #666;
}

.timeline-time {
  font-size: 0.875rem;
  color: #999;
  margin-top: 0.5rem;
  display: block;
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loading-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #666;
}

.loading-spinner i {
  animation: spin 1s linear infinite;
}

/* Action Buttons */
.actions {
  white-space: nowrap;
  display: flex;
  gap: 0.75rem;
  justify-content: flex-start;
  align-items: center;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #f8f9fc;
  border: 1px solid #e2e8f0;
  color: #010647;
  transition: all 0.2s ease;
}

.btn-icon:hover {
  background: #e2e8f0;
  transform: translateY(-2px);
}

.btn-icon i {
  font-size: 1.25rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .detail-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .filters {
    flex-direction: column;
    align-items: stretch;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .pagination-container {
    flex-direction: column;
    gap: 1rem;
    align-items: center;
  }
}
</style>
