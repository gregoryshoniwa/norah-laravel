<template>
  <div class="super-users-container">
    <div class="page-header">
      <h1><i class="ri-shield-user-line"></i> Super Admin Users</h1>
      <button class="btn-add" @click="openAddModal">
        <i class="ri-user-add-line"></i> Add User
      </button>
    </div>

    <div class="content-card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading">
            <tr v-for="user in users" :key="user.id">
              <td>#{{ user.id }}</td>
              <td>{{ fullName(user) }}</td>
              <td>{{ user.email }}</td>
              <td>
                <span :class="['status-badge', user.is_activated ? 'active' : 'inactive']">
                  {{ user.is_activated ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td class="actions">
                <button class="btn-icon" @click="editUser(user)" title="Edit">
                  <i class="ri-edit-line"></i>
                </button>
                <button
                  class="btn-icon delete"
                  :disabled="isCurrentUser(user.id)"
                  @click="confirmDelete(user)"
                  :title="isCurrentUser(user.id) ? 'Cannot delete yourself' : 'Delete'"
                >
                  <i class="ri-delete-bin-line"></i>
                </button>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="text-center">
                <div class="loading-spinner">
                  <i class="ri-loader-4-line spin"></i> Loading...
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-if="!loading && !users.length">
            <tr>
              <td colspan="6" class="empty-state">
                <i class="ri-inbox-line empty-icon"></i>
                <p>No super users found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal" v-if="showAddModal" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ editingUser ? 'Edit User' : 'Add User' }}</h3>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-group">
              <label>First Name</label>
              <input v-model="userForm.firstName" type="text" required />
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input v-model="userForm.lastName" type="text" required />
            </div>
            <div class="form-group">
              <label>Email</label>
              <input v-model="userForm.email" type="email" required />
            </div>
            <div class="form-group" v-if="!editingUser">
              <label>Password</label>
              <input v-model="userForm.password" type="password" required />
            </div>
            <div class="form-group" v-if="editingUser">
              <label class="toggle-label">
                <input type="checkbox" v-model="userForm.is_activated" />
                <span>Active</span>
              </label>
            </div>
            <div class="form-actions">
              <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary">
                {{ editingUser ? 'Update' : 'Create' }} User
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
  name: 'SuperUsers',
  data() {
    return {
      users: [],
      loading: false,
      showAddModal: false,
      editingUser: null,
      userForm: {
        firstName: '',
        lastName: '',
        email: '',
        password: '',
        is_activated: true
      }
    };
  },
  mounted() {
    this.loadUsers();
  },
  methods: {
    fullName(user) {
      return [user.first_name, user.last_name].filter(Boolean).join(' ') || '—';
    },
    formatDate(date) {
      if (!date) return '—';
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    isCurrentUser(userId) {
      try {
        const user = JSON.parse(localStorage.getItem('user'));
        return user && String(user.user_id) === String(userId);
      } catch {
        return false;
      }
    },
    async loadUsers() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/super/users');
        if (response.data.success) {
          this.users = response.data.data;
        }
      } catch (error) {
        this.$swal.fire('Error!', 'Failed to load users', 'error');
      } finally {
        this.loading = false;
      }
    },
    openAddModal() {
      this.editingUser = null;
      this.userForm = {
        firstName: '',
        lastName: '',
        email: '',
        password: '',
        is_activated: true
      };
      this.showAddModal = true;
    },
    editUser(user) {
      this.editingUser = user;
      this.userForm = {
        firstName: user.first_name || '',
        lastName: user.last_name || '',
        email: user.email || '',
        is_activated: !!user.is_activated
      };
      this.showAddModal = true;
    },
    async handleSubmit() {
      try {
        if (this.editingUser) {
          await axios.put(`/api/v1/super/users/${this.editingUser.id}`, {
            firstName: this.userForm.firstName,
            lastName: this.userForm.lastName,
            email: this.userForm.email,
            is_activated: this.userForm.is_activated
          });
          this.$swal.fire('Success!', 'User updated successfully', 'success');
        } else {
          await axios.post('/api/v1/super/users', {
            firstName: this.userForm.firstName,
            lastName: this.userForm.lastName,
            email: this.userForm.email,
            password: this.userForm.password
          });
          this.$swal.fire('Success!', 'User created successfully', 'success');
        }
        this.closeModal();
        this.loadUsers();
      } catch (error) {
        this.$swal.fire('Error!', error.response?.data?.message || 'Operation failed', 'error');
      }
    },
    async confirmDelete(user) {
      if (this.isCurrentUser(user.id)) {
        this.$swal.fire('Not allowed', 'You cannot delete yourself', 'warning');
        return;
      }
      const result = await this.$swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
      });
      if (result.isConfirmed) {
        try {
          await axios.delete(`/api/v1/super/users/${user.id}`);
          this.$swal.fire('Deleted!', 'User has been deleted.', 'success');
          this.loadUsers();
        } catch (error) {
          this.$swal.fire('Error!', 'Failed to delete user', 'error');
        }
      }
    },
    closeModal() {
      this.showAddModal = false;
      this.editingUser = null;
      this.userForm = {
        firstName: '',
        lastName: '',
        email: '',
        password: '',
        is_activated: true
      };
    }
  }
};
</script>

<style scoped>
.super-users-container {
  animation: fadeIn 0.5s ease;
  min-height: 100%;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-header h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-header h1 i {
  color: #f59e0b;
}

.btn-add {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #0f172a;
  border: none;
  padding: 0.6rem 1.25rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-add:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.content-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
  border: 1px solid rgba(15, 23, 42, 0.06);
  padding: 1.5rem;
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
}

tbody tr:hover td {
  background: #f8fafc;
}

.status-badge {
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-badge.active {
  background: rgba(34, 197, 94, 0.15);
  color: #16a34a;
}

.status-badge.inactive {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  background: #f1f5f9;
  border: none;
  color: #0f172a;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 8px;
  transition: all 0.2s;
}

.btn-icon:hover:not(:disabled) {
  background: #e2e8f0;
}

.btn-icon.delete {
  color: #dc2626;
}

.btn-icon.delete:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.15);
}

.btn-icon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.loading-spinner {
  padding: 2rem;
  text-align: center;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
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

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: 90%;
  max-width: 480px;
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

.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #334155;
  font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"] {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: #f59e0b;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.toggle-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #f59e0b;
}

.form-actions {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-primary {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #0f172a;
  border: none;
  padding: 0.6rem 1.25rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary:hover {
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
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

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-add {
    justify-content: center;
  }

  .modal-content {
    width: 95%;
    margin: 1rem;
  }
}
</style>
