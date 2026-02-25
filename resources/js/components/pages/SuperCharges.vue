<template>
  <div class="super-charges-container">
    <div class="page-header">
      <h2><i class="ri-money-cny-circle-line"></i> System Charges</h2>
      <button class="btn-primary" @click="openAddModal">
        <i class="ri-add-line"></i> Add Charge
      </button>
    </div>

    <div class="content-card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Source</th>
              <th>Category</th>
              <th>Currency</th>
              <th>Value</th>
              <th>Min Threshold</th>
              <th>Max Threshold</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="loading">
            <tr>
              <td colspan="10" class="text-center">
                <div class="loading-spinner">
                  <i class="ri-loader-4-line spin"></i> Loading...
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="charges.length === 0">
            <tr>
              <td colspan="10" class="text-center">
                <div class="empty-state">
                  <i class="ri-inbox-line empty-icon"></i>
                  <p>No system charges configured yet.</p>
                  <button class="btn-primary" @click="openAddModal">
                    <i class="ri-add-line"></i> Add your first charge
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr v-for="charge in charges" :key="charge.id">
              <td>#{{ charge.id }}</td>
              <td>{{ charge.charge_type }}</td>
              <td>{{ charge.charge_source }}</td>
              <td>{{ charge.charge_category }}</td>
              <td>{{ charge.currency }}</td>
              <td>{{ formatValue(charge.value) }}</td>
              <td>{{ formatValue(charge.min_threshold) }}</td>
              <td>{{ formatValue(charge.max_threshold) }}</td>
              <td>
                <span :class="['status-badge', charge.status === 'ACTIVE' ? 'active' : 'inactive']">
                  {{ charge.status }}
                </span>
              </td>
              <td class="actions">
                <button class="btn-icon" @click="editCharge(charge)" title="Edit">
                  <i class="ri-edit-line"></i>
                </button>
                <button class="btn-icon delete" @click="confirmDelete(charge)" title="Delete">
                  <i class="ri-delete-bin-line"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" v-if="showModal" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ editingCharge ? 'Edit Charge' : 'Add Charge' }}</h3>
          <button class="close-btn" @click="closeModal" aria-label="Close">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-grid">
              <div class="form-column">
                <div class="form-group">
                  <label>Charge Type</label>
                  <input v-model="form.chargeType" type="text" required placeholder="e.g. Transaction Fee" />
                </div>
                <div class="form-group">
                  <label>Charge Source</label>
                  <input v-model="form.chargeSource" type="text" required placeholder="e.g. EcoCash" />
                </div>
                <div class="form-group">
                  <label>Charge Category</label>
                  <input v-model="form.chargeCategory" type="text" required placeholder="e.g. Payment" />
                </div>
                <div class="form-group">
                  <label>Status</label>
                  <select v-model="form.status" required>
                    <option value="ACTIVE">ACTIVE</option>
                    <option value="INACTIVE">INACTIVE</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Currency</label>
                  <select v-model="form.currency" required>
                    <option value="USD">USD</option>
                    <option value="ZWL">ZWL</option>
                    <option value="ZAR">ZAR</option>
                  </select>
                </div>
              </div>
              <div class="form-column">
                <div class="form-group">
                  <label>Value</label>
                  <input v-model.number="form.value" type="number" step="0.01" min="0" required placeholder="0.00" />
                </div>
                <div class="form-group">
                  <label>Statement Narration</label>
                  <input v-model="form.statementNarration" type="text" required placeholder="Description for statements" />
                </div>
                <div class="form-group">
                  <label>Min Threshold</label>
                  <input v-model.number="form.minThreshold" type="number" step="0.01" min="0" required placeholder="0.00" />
                </div>
                <div class="form-group">
                  <label>Max Threshold</label>
                  <input v-model.number="form.maxThreshold" type="number" step="0.01" min="0" required placeholder="0.00" />
                </div>
                <div class="form-group">
                  <label>PL Account</label>
                  <input v-model="form.plAccount" type="text" required placeholder="Profit/Loss account" />
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="submitting">
                <i v-if="submitting" class="ri-loader-4-line spin"></i>
                {{ editingCharge ? 'Update' : 'Create' }} Charge
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
  name: 'SuperCharges',
  data() {
    return {
      charges: [],
      loading: false,
      submitting: false,
      showModal: false,
      editingCharge: null,
      form: {
        chargeType: '',
        chargeSource: '',
        chargeCategory: '',
        status: 'ACTIVE',
        currency: 'USD',
        value: 0,
        statementNarration: '',
        minThreshold: 0,
        maxThreshold: 0,
        plAccount: ''
      }
    };
  },
  mounted() {
    this.loadCharges();
  },
  methods: {
    async loadCharges() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/system/charges');
        this.charges = Array.isArray(response.data) ? response.data : (response.data?.data ?? []);
      } catch (error) {
        this.$swal.fire('Error!', error.response?.data?.message || 'Failed to load charges', 'error');
      } finally {
        this.loading = false;
      }
    },

    openAddModal() {
      this.editingCharge = null;
      this.resetForm();
      this.showModal = true;
    },

    editCharge(charge) {
      this.editingCharge = charge;
      this.form = {
        chargeType: charge.charge_type,
        chargeSource: charge.charge_source,
        chargeCategory: charge.charge_category,
        status: charge.status,
        currency: charge.currency,
        value: parseFloat(charge.value) || 0,
        statementNarration: charge.statement_narration || '',
        minThreshold: parseFloat(charge.min_threshold) || 0,
        maxThreshold: parseFloat(charge.max_threshold) || 0,
        plAccount: charge.pl_account || ''
      };
      this.showModal = true;
    },

    resetForm() {
      this.form = {
        chargeType: '',
        chargeSource: '',
        chargeCategory: '',
        status: 'ACTIVE',
        currency: 'USD',
        value: 0,
        statementNarration: '',
        minThreshold: 0,
        maxThreshold: 0,
        plAccount: ''
      };
    },

    async handleSubmit() {
      this.submitting = true;
      try {
        const payload = {
          chargeType: this.form.chargeType,
          chargeSource: this.form.chargeSource,
          chargeCategory: this.form.chargeCategory,
          status: this.form.status,
          currency: this.form.currency,
          value: this.form.value,
          statementNarration: this.form.statementNarration,
          minThreshold: this.form.minThreshold,
          maxThreshold: this.form.maxThreshold,
          plAccount: this.form.plAccount
        };

        if (this.editingCharge) {
          await axios.put(`/api/v1/system/charges/${this.editingCharge.id}`, payload);
          this.$swal.fire('Success!', 'Charge updated successfully', 'success');
        } else {
          await axios.post('/api/v1/system/charges/add', payload);
          this.$swal.fire('Success!', 'Charge created successfully', 'success');
        }
        this.closeModal();
        this.loadCharges();
      } catch (error) {
        this.$swal.fire('Error!', error.response?.data?.message || 'Operation failed', 'error');
      } finally {
        this.submitting = false;
      }
    },

    async confirmDelete(charge) {
      const result = await this.$swal.fire({
        title: 'Delete Charge?',
        text: `Are you sure you want to delete charge #${charge.id}? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it'
      });

      if (result.isConfirmed) {
        try {
          await axios.delete(`/api/v1/system/charges/${charge.id}`);
          this.$swal.fire('Deleted!', 'Charge has been deleted.', 'success');
          this.loadCharges();
        } catch (error) {
          this.$swal.fire('Error!', error.response?.data?.message || 'Failed to delete charge', 'error');
        }
      }
    },

    closeModal() {
      this.showModal = false;
      this.editingCharge = null;
      this.resetForm();
    },

    formatValue(val) {
      const n = parseFloat(val);
      if (isNaN(n)) return '—';
      return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    }
  }
};
</script>

<style scoped>
.super-charges-container {
  animation: fadeIn 0.3s ease;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #010647;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-header h2 i {
  color: #f59e0b;
}

.content-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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

thead th {
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
  color: #334155;
  transition: background 0.2s;
}

tbody tr:hover td {
  background: #f8f9fc;
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

.status-badge.active {
  background: rgba(0,184,148,0.1);
  color: #00b894;
}

.status-badge.active::before {
  background: #00b894;
}

.status-badge.inactive {
  background: rgba(255,71,87,0.1);
  color: #ff4757;
}

.status-badge.inactive::before {
  background: #ff4757;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  background: #f8f9fc;
  border: 1px solid #e2e8f0;
  color: #010647;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: #e2e8f0;
  transform: translateY(-1px);
}

.btn-icon.delete {
  background: rgba(255,71,87,0.08);
  border-color: rgba(255,71,87,0.2);
  color: #ff4757;
}

.btn-icon.delete:hover {
  background: rgba(255,71,87,0.15);
}

.btn-primary {
  background: linear-gradient(135deg, #010647 0%, #020968 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px rgba(1,6,71,0.2);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(1,6,71,0.3);
}

.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
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

.loading-spinner,
.text-center {
  padding: 2rem;
  text-align: center;
  color: #666;
}

.loading-spinner .spin {
  display: inline-block;
  animation: spin 1s linear infinite;
  margin-right: 0.5rem;
}

.empty-state {
  padding: 3rem 2rem;
  color: #999;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.empty-state .empty-icon {
  font-size: 3rem;
  color: #ccc;
  margin-bottom: 1rem;
  display: block;
}

.empty-state p {
  margin-bottom: 1.5rem;
  font-size: 1rem;
}

/* Modal */
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
  padding: 1rem;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 720px;
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
}

.modal-header h3 {
  margin: 0;
  color: #010647;
  font-size: 1.25rem;
}

.close-btn {
  background: none;
  border: none;
  color: #999;
  cursor: pointer;
  padding: 0.5rem;
  font-size: 1.25rem;
  line-height: 1;
  transition: color 0.2s;
}

.close-btn:hover {
  color: #ff4757;
}

.modal-body {
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #010647;
  font-weight: 500;
  font-size: 0.9rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 1rem;
  background: #f8f9fc;
  color: #0f172a;
  transition: all 0.3s ease;
}

.form-group input::placeholder,
.form-group input::-webkit-input-placeholder {
  color: #94a3b8;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #010647;
  outline: none;
  box-shadow: 0 0 0 3px rgba(1,6,71,0.1);
  background: white;
}

.form-group select {
  cursor: pointer;
  appearance: auto;
}

.form-actions {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f1f1;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

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

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .form-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .modal-content {
    max-height: 95vh;
  }

  .table-container {
    font-size: 0.875rem;
  }

  th, td {
    padding: 0.75rem;
  }
}
</style>
