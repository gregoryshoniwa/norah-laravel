<template>
  <div class="merchants-container animate-fade-in">
    <div class="page-header">
      <h2>Merchants Management</h2>
      <button class="btn-primary" @click="showAddMerchantModal = true">
        <i class="ri-store-2-line"></i> Add New Merchant
      </button>
    </div>

    <!-- Merchants Table -->
    <div class="content-card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Merchant Name</th>
              <th>Email</th>
              <th>Company</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading">
            <tr v-for="merchant in merchants" :key="merchant.merchant_id">
              <td>#{{ merchant.merchant_id }}</td>
              <td>{{ merchant.merchant_name }}</td>
              <td>{{ merchant.merchant_email || '—' }}</td>
              <td>{{ merchant.user?.company_name || '—' }}</td>
              <td>
                <span :class="['status-badge', isActive(merchant) ? 'active' : 'inactive']">
                  {{ isActive(merchant) ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="actions">
                <div class="action-buttons">
                  <button class="btn-icon" @click="editMerchant(merchant)" title="Edit">
                    <i class="ri-edit-line"></i>
                  </button>
                  <button class="btn-icon" @click="getMerchantSecret(merchant.merchant_id)" title="View Secret">
                    <i class="ri-key-line"></i>
                  </button>
                  <button
                    class="btn-icon"
                    :class="isActive(merchant) ? 'warning' : 'success'"
                    @click="toggleMerchantStatus(merchant)"
                    :title="isActive(merchant) ? 'Deactivate' : 'Activate'"
                  >
                    <i :class="isActive(merchant) ? 'ri-pause-circle-line' : 'ri-play-circle-line'"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="text-center">
                <div class="loading-spinner">Loading...</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Merchant Modal -->
    <div class="modal" v-if="showAddMerchantModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ editingMerchant ? 'Edit Merchant' : 'Add New Merchant' }}</h3>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-grid">
              <div class="form-column">
                <div class="form-group">
                  <label>Merchant Name</label>
                  <input v-model="merchantForm.merchant_name" type="text" required />
                </div>
                <div class="form-group">
                  <label>Email</label>
                  <input v-model="merchantForm.email" type="email" required />
                </div>
              </div>
              <div class="form-column">
                <div class="form-group">
                  <label>Return URL</label>
                  <input v-model="merchantForm.return_url" type="url" required />
                </div>
                <div class="form-group">
                  <label>Webhook URL (optional)</label>
                  <input v-model="merchantForm.web_service_url" type="url" placeholder="https://..." />
                </div>
                <div class="form-group" v-if="!editingMerchant">
                  <label>Password</label>
                  <input v-model="merchantForm.password" type="password" required />
                </div>
                <div class="form-group">
                  <label>Description</label>
                  <textarea v-model="merchantForm.merchant_description" rows="3"></textarea>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button type="button" class="btn-secondary" :disabled="submitting" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="submitting">
                <i v-if="submitting" class="ri-loader-4-line spin"></i>
                {{ submitting
                    ? (editingMerchant ? 'Updating...' : 'Creating...')
                    : (editingMerchant ? 'Update Merchant' : 'Create Merchant') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Merchants',
  data() {
    return {
      merchants: [],
      loading: false,
      submitting: false,
      showAddMerchantModal: false,
      editingMerchant: null,
      merchantForm: {
        merchant_name: '',
        email: '',
        merchant_description: '',
        return_url: '',
        web_service_url: '',
        password: ''
      }
    };
  },
  mounted() {
    this.loadMerchants();
  },
  methods: {
    async loadMerchants() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/admin/merchants');
        if (response.data.success) {
          this.merchants = response.data.data;
        }
      } catch (error) {
        this.$swal.fire('Error!', 'Failed to load merchants', 'error');
      } finally {
        this.loading = false;
      }
    },

    async handleSubmit() {
      if (this.submitting) return;
      this.submitting = true;
      try {
        if (this.editingMerchant) {
          await axios.put(`/api/v1/admin/merchants/${this.editingMerchant.merchant_id}`, this.merchantForm);
        } else {
          await axios.post('/api/v1/admin/create-merchant-account', this.merchantForm);
        }
        // Refresh list BEFORE closing the modal so the new row is in the
        // table by the time the user clicks OK on the success toast.
        await this.loadMerchants();
        this.closeModal();
        this.$swal.fire(
          'Success!',
          this.editingMerchant ? 'Merchant updated successfully' : 'Merchant created successfully',
          'success'
        );
      } catch (error) {
        this.$swal.fire('Error!', error.response?.data?.message || 'Operation failed', 'error');
      } finally {
        this.submitting = false;
      }
    },

    editMerchant(merchant) {
      this.editingMerchant = merchant;
      this.merchantForm = {
        merchant_name: merchant.merchant_name,
        email: merchant.merchant_email,
        merchant_description: merchant.merchant_description || '',
        return_url: merchant.return_url || '',
        web_service_url: merchant.web_service_url || '',
        password: ''
      };
      this.showAddMerchantModal = true;
    },

    async getMerchantSecret(merchantId) {
      try {
        const response = await axios.get(`/api/v1/merchant/secret/${merchantId}`);

        if (response.data && response.data.secret) {
          this.$swal.fire({
            title: 'Merchant Secret',
            text: response.data.secret,
            icon: 'info',
            confirmButtonText: 'Copy',
            showCancelButton: true,
            cancelButtonText: 'Close'
          }).then((result) => {
            if (result.isConfirmed) {
              navigator.clipboard.writeText(response.data.secret);
              this.$swal.fire('Copied!', 'Secret copied to clipboard', 'success');
            }
          });
        } else {
          this.$swal.fire('Error!', 'Failed to retrieve merchant secret', 'error');
        }
      } catch (error) {
        console.error('Error getting merchant secret:', error);
        this.$swal.fire('Error!', 'Failed to retrieve merchant secret', 'error');
      }
    },

    isActive(merchant) {
      return (merchant?.merchant_status || '').toUpperCase() === 'ACTIVE';
    },
    async toggleMerchantStatus(merchant) {
      try {
        const active = this.isActive(merchant);
        const action = active ? 'inactivate' : 'activate';
        const confirmResult = await this.$swal.fire({
          title: `${active ? 'Deactivate' : 'Activate'} Merchant?`,
          text: `Are you sure you want to ${active ? 'deactivate' : 'activate'} ${merchant.merchant_name}?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: active ? '#d33' : '#3085d6',
          cancelButtonColor: '#6c757d',
          confirmButtonText: `Yes, ${action} merchant`
        });

        if (confirmResult.isConfirmed) {
          const response = await axios.put(`/api/v1/admin/merchants/${merchant.merchant_id}/${action}`);

          if (response.data.success) {
            await this.loadMerchants();
            this.$swal.fire(
              'Success!',
              `Merchant ${active ? 'deactivated' : 'activated'} successfully`,
              'success'
            );
          } else {
            this.$swal.fire('Error!', response.data.message || 'Operation failed', 'error');
          }
        }
      } catch (error) {
        console.error(`Error toggling merchant status:`, error);
        this.$swal.fire('Error!', error.response?.data?.message || 'Operation failed', 'error');
      }
    },

    closeModal() {
      this.showAddMerchantModal = false;
      this.editingMerchant = null;
      this.merchantForm = {
        merchant_name: '',
        email: '',
        merchant_description: '',
        return_url: '',
        web_service_url: '',
        password: ''
      };
    }
  }
};
</script>

<style scoped>
.merchants-container {
  animation: fadeIn 0.5s ease;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #010647;
  margin: 0;
}

.content-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  padding: 1.5rem;
}

/* Table Styles */
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
  background: #f8f9fc;
  border-bottom: 2px solid #f1f1f1;
  font-weight: 600;
  color: #010647;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #f1f1f1;
  transition: all 0.3s ease;
}

tr {
  animation: slideIn 0.5s ease;
  animation-fill-mode: both;
}

tbody tr:hover td {
  background: #f8f9fc;
}

/* Status Badge */
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

.active {
  background: rgba(0,184,148,0.1);
  color: #00b894;
}

.active::before {
  background: #00b894;
}

.inactive {
  background: rgba(255,71,87,0.1);
  color: #ff4757;
}

.inactive::before {
  background: #ff4757;
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
  animation: scaleIn 0.3s ease;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 2px solid #f1f1f1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: #010647;
  font-size: 1.25rem;
}

.modal-body {
  padding: 1.5rem;
}

/* Form Grid Layout */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #010647;
  font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: #f8f9fc;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #010647;
  box-shadow: 0 0 0 3px rgba(1,6,71,0.1);
  outline: none;
  background: white;
}

.form-actions {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f1f1;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

/* Button Styles */
.btn-primary {
  background: linear-gradient(135deg, #010647 0%, #020968 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px rgba(1,6,71,0.2);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(1,6,71,0.3);
}

.btn-secondary {
  background: #f1f1f1;
  color: #666;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: #e1e1e1;
}

/* Loading Spinner */
.loading-spinner {
  padding: 2rem;
  text-align: center;
  color: #666;
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
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

/* Responsive Design */
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .modal-content {
    width: 95%;
    margin: 1rem;
  }
}

.btn-primary:disabled,
.btn-secondary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  transform: none !important;
}

/* Roomier action icon column. The flex container lives INSIDE the td so the
   table cell keeps its normal vertical sizing and the row bottom border
   stays at a consistent height. */
td.actions { vertical-align: middle; white-space: nowrap; }
td.actions .action-buttons {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}
td.actions .btn-icon {
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  padding: 0;
}
td.actions .btn-icon:hover {
  background: #f1f5f9;
  color: #1e293b;
  border-color: #cbd5e1;
}
td.actions .btn-icon.success {
  color: #16a34a;
  border-color: #bbf7d0;
}
td.actions .btn-icon.success:hover { background: #f0fdf4; }
td.actions .btn-icon.warning {
  color: #d97706;
  border-color: #fde68a;
}
td.actions .btn-icon.warning:hover { background: #fffbeb; }
table td { vertical-align: middle; }
.btn-primary .spin,
.btn-secondary .spin {
  display: inline-block;
  margin-right: 6px;
  animation: spin 0.9s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
