<template>
     <div class="min-h-screen bg-gradient-to-br from-[#0f172a] via-[#1e293b] to-[#4f46e5] py-8 px-4 sm:px-6 lg:px-8 animate-gradient">
        <br><br>
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <!-- Logo -->
        <div class="flex justify-center">
          <div class="h-16 w-16 rounded-full bg-[var(--color-primary)] flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--color-primary-foreground)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>

        <!-- Title -->
        <h2 class="mt-6 text-center text-3xl font-extrabold text-white">Account Confirmation</h2>
      </div>

      <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <!-- Progress Steps -->
          <div class="px-6 pt-8 pb-6">
                <div class="relative flex justify-center items-center">
                    <!-- Step circles -->
                    <div class="flex justify-between items-center w-full max-w-xs mx-auto relative z-10">
                        <div
                            v-for="(step, index) in steps"
                            :key="index"
                            class="flex flex-col items-center"
                        >
                            <div
                            class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
                            :class="[
                                currentStep > index
                                ? 'bg-[var(--color-primary)] text-[var(--color-primary-foreground)]'
                                : currentStep === index
                                    ? 'bg-[var(--color-primary)] text-[var(--color-primary-foreground)]'
                                    : 'bg-gray-200 text-gray-500'
                            ]"
                            >
                            {{ index + 1 }}
                            </div>
                            <div
                            class="text-xs mt-2 text-center"
                            :class="currentStep >= index ? 'text-[var(--color-primary)] font-medium' : 'text-gray-500'"
                            >
                            {{ step.name }}
                            </div>
                        </div>
                    </div>

                    <!-- Connecting lines (positioned behind the circles) -->
                    <div class="absolute top-6 left-0 right-0 flex justify-center">
                    <div class="h-1 bg-gray-200 w-full max-w-xs mx-auto">
                        <div
                        class="h-full bg-[var(--color-primary)] transition-all duration-300"
                        :style="`width: ${currentStep > 0 ? (currentStep > 1 ? '100%' : '50%') : '0%'}`"
                        ></div>
                    </div>
                    </div>
                </div>
                </div>

          <!-- Loading State -->
          <div v-if="loading" class="text-center py-6">
            <svg class="animate-spin h-10 w-10 text-[var(--color-primary)] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-4 text-gray-600">Verifying your account...</p>
          </div>

          <!-- Success State -->
          <div v-else-if="status === 'success'" class="text-center py-6">
            <div class="rounded-full bg-green-100 p-3 mx-auto w-16 h-16 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h3 class="mt-4 text-xl font-medium text-gray-900">Account Confirmed!</h3>
            <p class="mt-2 text-gray-600">{{ message }}</p>
            <div class="mt-6">
              <button
                @click="goToLogin"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-[var(--color-primary-foreground)] bg-[var(--color-primary)] hover:bg-[#4db6ac] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                Continue to Login
              </button>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="status === 'error'" class="text-center py-6">
            <div class="rounded-full bg-red-100 p-3 mx-auto w-16 h-16 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h3 class="mt-4 text-xl font-medium text-gray-900">Confirmation Failed</h3>
            <p class="mt-2 text-gray-600">{{ message }}</p>
            <div class="mt-6">
              <button
                 @click="showModal = true"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-[var(--color-primary-foreground)] bg-[var(--color-primary)] hover:bg-[#4db6ac] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                Request New Link
              </button>
            </div>
          </div>
        </div>


             <!-- Modal -->
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Request New Confirmation Link</h3>
                <form @submit.prevent="submitNewLink">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input
                    v-model="email"
                    type="email"
                    id="email"
                    class="mt-1 block w-full border-blue-400 rounded-lg shadow-sm focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] hover:border-blue-500 bg-gray-50 text-gray-700 placeholder-gray-400 transition duration-200 ease-in-out focus:bg-white focus:outline-none px-4 py-3"
                    placeholder="Enter your registered email"
                    required
                    />
                </div>
                <div class="flex justify-end space-x-2">
                    <button
                    type="button"
                    @click="showModal = false"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                    >
                    Cancel
                    </button>
                    <button
                    type="submit"
                    class="px-4 py-2 bg-[var(--color-primary)] text-white rounded-md hover:bg-[#4db6ac]"
                    >
                    Submit
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
    data() {
      return {
        loading: true,
        message: '',
        status: '', // 'success', 'error', or 'expired'
        currentStep: 1, // Current step (1-based index)
        steps: [
            { name: 'Sign Up' },
            { name: 'Confirm' },
            { name: 'Complete' },
        ],
        showModal: false, // Controls the visibility of the modal
        email: '',
      };
    },
    async created() {
      const token = this.$route.query.token;

      if (!token) {
        this.status = 'error';
        this.message = 'Invalid confirmation link. No token provided.';
        this.loading = false;
        return;
      }

      try {
        const response = await axios.post('/api/v1/confirm-account', { token });
        this.message = response.data.message || 'Your account has been successfully confirmed. You can now log in.';
        this.status = 'success';
      } catch (error) {
        if (error.response?.status === 410) {
          this.status = 'expired';
          this.message = error.response.data.message || 'This confirmation link has expired.';
        } else {
          this.status = 'error';
          this.message = error.response?.data?.message || 'An error occurred while confirming your account.';
        }
      } finally {
        this.loading = false;
      }
    },
    methods: {
      goToLogin() {
        this.$router.push('/login');
      },
      async submitNewLink() {
      this.loading = true;
      this.showModal = false;

      try {
        await axios.post('/api/v1/auth/resend-confirmation-email', {
          email: this.email,
        });

        this.status = 'success';
        this.message = 'A new confirmation link has been sent to your email address.';
      } catch (error) {
        this.status = 'error';
        this.message = error.response?.data?.message || 'Failed to send a new confirmation link.';
      } finally {
        this.loading = false;
      }
    },
      async requestNewLink() {
        this.loading = true;

        try {
          await axios.post('/api/v1/auth/resend-confirmation-email', {
            email: this.$route.query.token,
          });

          this.status = 'success';
          this.message = 'A new confirmation link has been sent to your email address.';
        } catch (error) {
          this.status = 'error';
          this.message = error.response?.data?.message || 'Failed to send a new confirmation link.';
        } finally {
          this.loading = false;
        }
      },
    },
  };
  </script>

  <style>
   @keyframes gradient {
  0% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0% 50%;
  }
}

.animate-gradient {
  background-size: 200% 200%;
  animation: gradient 6s ease infinite;
}
  :root {
    --color-primary: #4f46e5;
    --color-primary-foreground: white;
  }
  </style>
