<template>
  <div class="users-container animate-fade-in">
    <div class="page-header">
      <h2>Users Management</h2>
      <button class="btn-primary" @click="showAddUserModal = true">
        <i class="ri-user-add-line"></i> Add New User
      </button>
    </div>

    <!-- Users Table -->
    <div class="content-card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading">
            <tr v-for="user in users" :key="user.id">
              <td>#{{ user.id }}</td>
              <td>{{ user.fullName }}</td>
              <td>{{ user.email }}</td>
              <td>
                <span :class="['role-badge', user.role.toLowerCase()]">
                  {{ user.role }}
                </span>
              </td>
              <td>
                <span :class="['status-badge', user.status ? 'active' : 'inactive']">
                  {{ user.status ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="actions">
                <button class="btn-icon" @click="editUser(user)" title="Edit">
                  <i class="ri-edit-line"></i>
                </button>
                <button class="btn-icon" @click="getUserSecret(user.id)" title="View Secret">
                  <i class="ri-key-line"></i>
                </button>
                <button class="btn-icon delete" @click="confirmDelete(user)" title="Delete">
                  <i class="ri-delete-bin-line"></i>
                </button>
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

    <!-- Add/Edit User Modal -->
    <div class="modal" v-if="showAddUserModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ editingUser ? 'Edit User' : 'Add New User' }}</h3>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-grid">
              <div class="form-column">
                <div class="form-group">
                  <label>Full Name</label>
                  <input v-model="userForm.fullName" type="text" required />
                </div>
                <div class="form-group">
                  <label>Email</label>
                  <input v-model="userForm.email" type="email" required />
                </div>
                <div class="form-group" v-if="!editingUser">
                  <label>Password</label>
                  <input v-model="userForm.password" type="password" required />
                </div>
              </div>
              <div class="form-column">
                <div class="form-group">
                  <label>Role</label>
                  <select v-model="userForm.role" required>
                    <option value="ADMIN">Administrator</option>
                    <option value="USER">Regular User</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Status</label>
                  <select v-model="userForm.status">
                    <option :value="true">Active</option>
                    <option :value="false">Inactive</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Notes</label>
                  <textarea v-model="userForm.notes" rows="3" placeholder="Optional notes about the user"></textarea>
                </div>
              </div>
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
  name: 'Users',
  data() {
    return {
      users: [],
      loading: false,
      showAddUserModal: false,
      editingUser: null,
      userForm: {
        fullName: '',
        email: '',
        password: '',
        role: 'USER',
        status: true,
        notes: ''
      }
    };
  },
  mounted() {
    this.loadUsers();
  },
  methods: {
    async loadUsers() {
      this.loading = true;
      try {
        const response = await axios.get('/api/v1/admin/users');
        if (response.data.success) {
          this.users = response.data.data;
        }
      } catch (error) {
        this.$swal.fire('Error!', 'Failed to load users', 'error');
      } finally {
        this.loading = false;
      }
    },

    async handleSubmit() {
      try {
        if (this.editingUser) {
          await axios.put(`/api/v1/admin/users/${this.editingUser.id}`, this.userForm);
          this.$swal.fire('Success!', 'User updated successfully', 'success');
        } else {
          await axios.post('/api/v1/admin/secondary-admin-sign-up', this.userForm);
          this.$swal.fire('Success!', 'User created successfully', 'success');
        }
        this.closeModal();
        this.loadUsers();
      } catch (error) {
        this.$swal.fire('Error!', error.response?.data?.message || 'Operation failed', 'error');
      }
    },

    editUser(user) {
      this.editingUser = user;
      this.userForm = {
        fullName: user.fullName,
        email: user.email,
        role: user.role,
        status: user.status,
        notes: user.notes || '',
        password: ''
      };
      this.showAddUserModal = true;
    },

    async getUserSecret(userId) {
      try {
        const response = await axios.get(`/api/v1/user/secret/${userId}`);
        this.$swal.fire({
          title: 'User Secret',
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
      } catch (error) {
        this.$swal.fire('Error!', 'Failed to get user secret', 'error');
      }
    },

    async confirmDelete(user) {
      const result = await this.$swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      });

      if (result.isConfirmed) {
        try {
          await axios.delete(`/api/v1/admin/users/${user.id}`);
          this.$swal.fire('Deleted!', 'User has been deleted.', 'success');
          this.loadUsers();
        } catch (error) {
          this.$swal.fire('Error!', 'Failed to delete user', 'error');
        }
      }
    },

    closeModal() {
      this.showAddUserModal = false;
      this.editingUser = null;
      this.userForm = {
        fullName: '',
        email: '',
        password: '',
        role: 'USER',
        status: true,
        notes: ''
      };
    }
  }
};
</script>

<style scoped>
/* Base Styles */
.users-container {
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

/* Role & Status Badges */
.role-badge,
.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.role-badge.admin {
  background: rgba(1,6,71,0.1);
  color: #010647;
}

.role-badge.user {
  background: rgba(45,55,72,0.1);
  color: #2d3748;
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

/* Action Buttons */
.actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  background: none;
  border: none;
  color: #010647;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.btn-icon:hover {
  background: rgba(1,6,71,0.1);
}

.btn-icon.delete {
  color: #ff4757;
}

.btn-icon.delete:hover {
  background: rgba(255,71,87,0.1);
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
</style>
