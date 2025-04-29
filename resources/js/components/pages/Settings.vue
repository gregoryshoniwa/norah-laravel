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
      }
    }
  },
  mounted() {
    this.loadSettings();
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
    }
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
  gap: 2rem;
  margin-bottom: 2rem;
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
</style>
