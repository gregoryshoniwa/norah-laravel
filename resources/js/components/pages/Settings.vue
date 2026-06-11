<template>
  <div class="settings-container animate-fade-in">
    <div class="page-header">
      <h2>System Settings</h2>
    </div>

    <div class="content-card">
      <div class="settings-grid">
        <!-- Currency Settings -->
        <div class="settings-section">
          <h3 class="settings-title">
            <i class="ri-money-dollar-circle-line"></i>
            Currency Settings
          </h3>
          <div class="settings-content">
            <div class="form-group">
              <label>Default Currency</label>
              <select v-model="settings.defaultCurrency">
                <option value="USD">USD - US Dollar</option>
                <option value="ZWG">ZWG - Zimbabwe Gold</option>
                <option value="ZAR">ZAR - South African Rand</option>
                <option value="BWP">BWP - Botswana Pula</option>
                <option value="GBP">GBP - British Pound</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Notification Settings -->
        <div class="settings-section">
          <h3 class="settings-title">
            <i class="ri-notification-3-line"></i>
            Notification Settings
          </h3>
          <div class="settings-content">
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="settings.emailNotifications">
                Email Notifications
              </label>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="settings.smsNotifications">
                SMS Notifications
              </label>
            </div>
          </div>
        </div>

        <!-- Security Settings -->
        <div class="settings-section">
          <h3 class="settings-title">
            <i class="ri-shield-keyhole-line"></i>
            Security Settings
          </h3>
          <div class="settings-content">
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="settings.twoFactorAuth">
                Two-Factor Authentication
              </label>
            </div>
            <div class="form-group">
              <label>Session Timeout (minutes)</label>
              <input type="number" v-model="settings.sessionTimeout" min="5" max="120">
            </div>
          </div>
        </div>

        <!-- API Settings -->
        <div class="settings-section">
          <h3 class="settings-title">
            <i class="ri-code-line"></i>
            API Settings
          </h3>
          <div class="settings-content">
            <div class="form-group">
              <label>API Rate Limit (requests/minute)</label>
              <input type="number" v-model="settings.apiRateLimit" min="10" max="1000">
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="settings.enableApiLogs">
                Enable API Logs
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="settings-actions">
        <button class="btn-secondary" @click="resetSettings">Reset</button>
        <button class="btn-primary" @click="saveSettings">
          <i class="ri-save-line"></i>
          Save Changes
        </button>
      </div>
    </div>

    <!-- Company / integration settings -->
    <div class="content-card">
      <div class="settings-section full-width">
        <h3 class="settings-title">
          <i class="ri-building-line"></i>
          Company &amp; Integration
        </h3>
        <p class="settings-help">These are sent on transaction redirects and webhooks. Update once - changes apply to all future transactions.</p>
        <div class="settings-content company-grid">
          <div class="form-group">
            <label>Company name</label>
            <input v-model="profile.company_name" type="text" maxlength="255" />
          </div>
          <div class="form-group">
            <label>Return URL</label>
            <input v-model="profile.return_url" type="url" placeholder="https://yourapp.com/payment/return" maxlength="500" />
          </div>
          <div class="form-group">
            <label>Webhook URL</label>
            <input v-model="profile.web_service_url" type="url" placeholder="https://yourapp.com/webhooks/norah" maxlength="500" />
          </div>
        </div>
        <div class="settings-actions">
          <button class="btn-primary" :disabled="savingProfile" @click="saveProfile">
            <i class="ri-save-line"></i>
            {{ savingProfile ? 'Saving...' : 'Save company settings' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Bank for payouts -->
    <div class="content-card">
      <div class="settings-section full-width">
        <h3 class="settings-title">
          <i class="ri-bank-line"></i>
          Bank account for payouts
        </h3>
        <p class="settings-help">
          The gateway uses these to send your payouts. <strong>Payouts cannot be created until bank, account name, and account number are filled in.</strong>
        </p>

        <div v-if="profile.id && !profile.bank_complete" class="bank-warning">
          <i class="ri-error-warning-line"></i>
          Bank details are incomplete. Payouts to you are currently blocked.
        </div>

        <div class="settings-content company-grid">
          <div class="form-group">
            <label>Bank name *</label>
            <input v-model="profile.bank_name" type="text" maxlength="120" placeholder="e.g. CBZ Bank" />
          </div>
          <div class="form-group">
            <label>Branch</label>
            <input v-model="profile.bank_branch" type="text" maxlength="120" />
          </div>
          <div class="form-group">
            <label>Account name *</label>
            <input v-model="profile.bank_account_name" type="text" maxlength="120" />
          </div>
          <div class="form-group">
            <label>Account number *</label>
            <input v-model="profile.bank_account_number" type="text" maxlength="50" />
          </div>
          <div class="form-group">
            <label>SWIFT / BIC (optional)</label>
            <input v-model="profile.bank_swift_code" type="text" maxlength="20" />
          </div>
        </div>
        <div class="settings-actions">
          <button class="btn-primary" :disabled="savingBank" @click="saveBank">
            <i class="ri-save-line"></i>
            {{ savingBank ? 'Saving...' : 'Save bank details' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Settings',
  data() {
    return {
      loading: false,
      settings: {
        defaultCurrency: 'USD',
        emailNotifications: true,
        smsNotifications: false,
        twoFactorAuth: false,
        sessionTimeout: 30,
        apiRateLimit: 100,
        enableApiLogs: true
      },
      defaultSettings: {
        defaultCurrency: 'USD',
        emailNotifications: true,
        smsNotifications: false,
        twoFactorAuth: false,
        sessionTimeout: 30,
        apiRateLimit: 100,
        enableApiLogs: true
      },
      profile: {
        id: null,
        company_name: '',
        return_url: '',
        web_service_url: '',
        bank_name: '',
        bank_branch: '',
        bank_account_name: '',
        bank_account_number: '',
        bank_swift_code: '',
        bank_complete: false,
      },
      savingProfile: false,
      savingBank: false,
    }
  },
  mounted() {
    this.loadSettings();
    this.loadProfile();
  },
  methods: {
    async loadSettings() {
      this.loading = true;
      try {
        // Try to load settings from localStorage first
        const savedSettings = localStorage.getItem('appSettings');
        if (savedSettings) {
          this.settings = JSON.parse(savedSettings);
          this.loading = false;
          return;
        }

        // If no localStorage settings, try to load from API
        const response = await axios.get('/api/v1/settings');
        if (response.data.success) {
          this.settings = response.data.data;
          // Save to localStorage for future use
          localStorage.setItem('appSettings', JSON.stringify(this.settings));
        }
      } catch (error) {
        console.error('Error loading settings:', error);
        // If API fails, use default settings
        this.settings = { ...this.defaultSettings };
      } finally {
        this.loading = false;
      }
    },

    async saveSettings() {
      try {
        this.loading = true;

        // Save to localStorage
        localStorage.setItem('appSettings', JSON.stringify(this.settings));

        // Try to save to API
        try {
          const response = await axios.post('/api/v1/settings', this.settings);
          if (response.data.success) {
            this.$swal.fire({
              title: 'Success!',
              text: 'Settings saved successfully',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
          }
        } catch (apiError) {
          console.error('API error when saving settings:', apiError);
          // Still show success if localStorage save worked
          this.$swal.fire({
            title: 'Settings Saved Locally',
            text: 'Settings saved to your browser, but could not be saved to the server',
            icon: 'info',
            timer: 3000,
            showConfirmButton: false
          });
        }
      } catch (error) {
        console.error('Error saving settings:', error);
        this.$swal.fire('Error!', 'Failed to save settings', 'error');
      } finally {
        this.loading = false;
      }
    },

    resetSettings() {
      this.$swal.fire({
        title: 'Are you sure?',
        text: 'This will reset all settings to default values',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, reset settings'
      }).then((result) => {
        if (result.isConfirmed) {
          // Reset to default values
          this.settings = { ...this.defaultSettings };

          // Clear localStorage
          localStorage.removeItem('appSettings');

          // Try to reset on API
          try {
            axios.delete('/api/v1/settings/reset');
          } catch (error) {
            console.error('Error resetting settings on API:', error);
          }

          this.$swal.fire('Reset!', 'Settings have been reset to defaults', 'success');
        }
      });
    },

    async loadProfile() {
      try {
        const { data } = await axios.get('/api/v1/profile');
        if (data.success) this.profile = { ...this.profile, ...data.data };
      } catch (e) {
        console.error('Error loading profile', e);
      }
    },

    async saveProfile() {
      this.savingProfile = true;
      try {
        const payload = {
          company_name: this.profile.company_name || null,
          return_url: this.profile.return_url || null,
          web_service_url: this.profile.web_service_url || null,
        };
        const { data } = await axios.put('/api/v1/profile', payload);
        if (data.success) this.profile = { ...this.profile, ...data.data };
        this.$swal.fire('Saved', 'Company settings updated.', 'success');
      } catch (e) {
        this.$swal.fire('Error', e.response?.data?.message || 'Failed to save.', 'error');
      } finally {
        this.savingProfile = false;
      }
    },

    async saveBank() {
      this.savingBank = true;
      try {
        const payload = {
          bank_name: this.profile.bank_name || null,
          bank_branch: this.profile.bank_branch || null,
          bank_account_name: this.profile.bank_account_name || null,
          bank_account_number: this.profile.bank_account_number || null,
          bank_swift_code: this.profile.bank_swift_code || null,
        };
        const { data } = await axios.put('/api/v1/profile/bank', payload);
        if (data.success) this.profile = { ...this.profile, ...data.data };
        this.$swal.fire('Saved', this.profile.bank_complete ? 'Bank details saved. Payouts can now be issued to you.' : 'Bank details saved (still incomplete).', 'success');
      } catch (e) {
        this.$swal.fire('Error', e.response?.data?.message || 'Failed to save.', 'error');
      } finally {
        this.savingBank = false;
      }
    },
  }
}
</script>

<style scoped>
.settings-container {
  animation: fadeIn 0.5s ease;
}

.settings-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
  padding: 1.5rem;
  margin-bottom: 0;
}

/* The System Settings parent card: only this card has settings-actions as a
   direct child of content-card (the Company/Bank cards put theirs inside the
   inner section). Style that direct-child footer to match the rest. */
.content-card > .settings-actions {
  padding: 1.1rem 1.5rem;
  margin: 0;
  border-top: 1px solid #eef2f7;
  background: #fafbfd;
}

/* The four sub-cards inside the grid - lighter, no hover lift */
.settings-grid .settings-section {
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.settings-grid .settings-section:hover {
  transform: none;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.settings-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  overflow: hidden;
  transition: all 0.3s ease;
}

.settings-section:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}

.settings-title {
  margin: 0;
  padding: 1.25rem;
  background: #f8f9fc;
  border-bottom: 2px solid #f1f1f1;
  color: #010647;
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.settings-content {
  padding: 1.5rem;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #666;
  font-weight: 500;
}

.form-group input[type="number"],
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: #f8f9fc;
}

.form-group input[type="number"]:focus,
.form-group select:focus {
  border-color: #010647;
  box-shadow: 0 0 0 3px rgba(1,6,71,0.1);
  outline: none;
  background: white;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  border-radius: 4px;
  cursor: pointer;
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f1f1;
}

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

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Responsive Design */
@media (max-width: 768px) {
  .settings-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
}

/* Stand-alone settings cards (Company & Bank). Each one is its own
   content-card so they stack with breathing room between them. */
.settings-container .content-card + .content-card { margin-top: 2rem; }
.settings-container .content-card {
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 4px 16px rgba(15, 23, 42, 0.05);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  background: white;
}

.settings-section.full-width {
  width: 100%;
  background: white;
  box-shadow: none;
  border-radius: 0;
}
.settings-section.full-width:hover { transform: none; box-shadow: none; }

.settings-section.full-width .settings-title {
  background: linear-gradient(180deg, #fbfcfe, #f4f6fb);
  border-bottom: 1px solid #e2e8f0;
  padding: 1.1rem 1.5rem;
}

.settings-section.full-width .settings-actions {
  padding: 1.1rem 1.5rem;
  margin-top: 0;
  border-top: 1px solid #eef2f7;
  background: #fafbfd;
}
.settings-help {
  margin: 1.5rem 1.5rem 0 1.5rem;
  padding: 0.8rem 1rem;
  background: #f8fafc;
  border-left: 3px solid #6366f1;
  color: #475569;
  font-size: 0.88rem;
  border-radius: 6px;
}
.settings-section.full-width .settings-content { padding: 1.5rem; }
.settings-help strong { color: #1e293b; }

/* Grid for company + bank fields - lives inside .settings-content so
   it picks up its 1.5rem padding. Reset the form-group margin so the grid
   gap is the single source of truth for spacing. */
.company-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}
.company-grid .form-group { margin-bottom: 0; }
.company-grid .form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #475569;
  font-weight: 500;
  font-size: 0.9rem;
}
.company-grid .form-group input[type="text"],
.company-grid .form-group input[type="url"],
.company-grid .form-group input[type="email"],
.company-grid .form-group input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  background: #f8f9fc;
  color: #0f172a;
  box-sizing: border-box;
  transition: all 0.2s ease;
  font-family: inherit;
}
.company-grid .form-group input:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
  outline: none;
  background: white;
}
.company-grid .form-group input::placeholder { color: #94a3b8; }

.bank-warning {
  margin: 1.25rem 1.5rem 0 1.5rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}
.bank-warning i { font-size: 1.1rem; }
</style>
