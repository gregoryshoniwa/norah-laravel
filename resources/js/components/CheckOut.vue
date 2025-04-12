



<template>
    <div class="min-h-screen bg-gradient-to-br from-[#0f172a] via-[#1e293b] to-[#4f46e5] py-8 px-4 sm:px-6 lg:px-8 animate-gradient">
        <loader v-if="isLoading" />
        <br>
      <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden">
        <!-- Merchant Header -->
        <div class="p-6 bg-primary text-white">
          <h3 class="text-xl font-bold text-white" v-if="message">{{ message }}</h3>
          <div  v-if="tokenData" class="flex items-center">
            <div class="h-12 w-12 bg-white rounded-full flex items-center justify-center">
              <img
                :src="tokenData.logo || 'assets/placeholder.png'"
                alt="Merchant logo"
                class="h-11 w-11 object-contain rounded-full"
              />
            </div>
            <div class="ml-4">
              <h3 class="text-xl font-bold text-white">{{ tokenData.name }}</h3>
              <div class="text-sm opacity-90 text-white">{{ tokenData.description }}</div>
            </div>
          </div>
          <!-- <div class="mt-3 text-sm">
            <p class="flex items-center">
              <mail-icon class="h-4 w-4 mr-2" />
              {{ merchant.email }}
            </p>
            <p class="flex items-center">
              <globe-icon class="h-4 w-4 mr-2" />
              {{ merchant.website }}
            </p>
          </div> -->
        </div>

        <!-- Payment Summary -->
        <div class="p-4 border-b">
          <h4 class="text-lg font-semibold text-gray-800 mb-4">Payment Summary</h4>
          <div class="space-y-2">

            <div class="flex justify-between">
              <span class="text-gray-600">Transaction Amount</span>
              <span class="font-medium">{{ tokenData?.currency || ''}} {{ formatAmount(tokenData?.amount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Transaction Charge</span>
              <span class="font-medium">{{ tokenData?.currency || '' }} {{ formatAmount(tokenData?.charge) }}</span>
            </div>
            <div class="h-px bg-gray-200 my-2"></div>
            <div class="flex justify-between">
              <span class="font-semibold">Total Amount</span>
              <span class="font-bold text-lg">{{ tokenData?.currency || ''}} {{ formatAmount(tokenData?.totalAmount) }}</span>
            </div>
          </div>
        </div>

        <!-- Main Content Area -->
        <div v-if="!isProcessing">
             <!-- Stepper Progress -->
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
                        ? 'bg-primary text-white'
                        : currentStep === index
                            ? 'bg-primary text-white'
                            : 'bg-gray-200 text-gray-500'
                    ]"
                    >
                    {{ index + 1 }}
                    </div>
                    <div class="text-xs mt-2 text-center" :class="currentStep >= index ? 'text-primary font-medium' : 'text-gray-500'">
                    {{ step.name }}
                    </div>
                </div>
                </div>

                <!-- Connecting lines (positioned behind the circles) -->
                <div class="absolute top-6 left-0 right-0 flex justify-center">
                <div class="h-1 bg-gray-200 w-full max-w-xs mx-auto">
                    <div
                    class="h-full bg-primary transition-all duration-300"
                    :style="`width: ${currentStep > 0 ? (currentStep > 1 ? '100%' : '50%') : '0%'}`"
                    ></div>
                </div>
                </div>
            </div>
            </div>

            <!-- Step Content -->
            <div class="pr-6 pl-6">
            <transition name="fade" mode="out-in">
                <!-- Step 1: Payment Method Selection -->

                <div v-if="currentStep === 0" key="step1" class="space-y-4">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Select Payment Method</h4>

                    <!-- Payment Methods in a Single Row -->
                    <div class="flex justify-between gap-2">
                        <div
                        v-for="method in paymentMethods"
                        :key="method.id"
                        @click="selectPaymentMethod(method.id)"
                        class="border rounded-lg cursor-pointer transition-colors flex items-center justify-center"
                        :class="selectedMethod === method.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-gray-50'"
                        style="width: 90px; height: 60px;"
                        >
                        <img
                            :src="method.iconUrl"
                            :alt="method.name"
                            :style="{
                                height: method.id === 'visa' ? '16px' : method.id === 'mastercard' ? '32px' : method.id === 'zimswitch' ? '32px' : method.id === 'innbuck' ? '16px' : method.id === 'ecocash' ? '18px' : '50px',
                                width: method.id === 'visa' ? 'auto' : method.id === 'mastercard' ? 'auto' : method.id === 'zimswitch' ? 'auto' : method.id === 'innbuck' ? 'auto' : method.id === 'ecocash' ? 'auto' : '50px'
                            }"
                            class="w-auto"

                        />
                        </div>
                    </div>
                </div>

                <!-- <div v-if="currentStep === 0" key="step1" class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Select Payment Method</h2>

                <div class="grid grid-cols-1 gap-3">
                    <div
                    v-for="method in paymentMethods"
                    :key="method.id"
                    @click="selectPaymentMethod(method.id)"
                    class="border rounded-lg p-4 cursor-pointer transition-colors"
                    :class="selectedMethod === method.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-gray-50'"
                    >
                    <div class="flex items-center">
                        <div class="w-10 h-10 flex items-center justify-center">
                        <component :is="method.icon" v-if="method.component" class="h-6 w-6" />
                        <img v-else :src="method.iconUrl" :alt="method.name" class="h-6 w-auto object-contain" />
                        </div>
                        <div class="ml-3">
                        <span class="font-medium text-gray-900">{{ method.name }}</span>
                        </div>
                        <div class="ml-auto">
                        <circle-check class="h-5 w-5 text-primary" v-if="selectedMethod === method.id" />
                        </div>
                    </div>
                    </div>
                </div>
                </div> -->

                <!-- Step 2: Payment Details -->
                <div v-else-if="currentStep === 1" key="step2" class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Payment Details</h2>

                <!-- Card Payment Form -->
                <div v-if="isCardPayment" class="space-y-4">
                    <div>
                    <label for="cardNumber" class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                    <input
                        type="text"
                        id="cardNumber"
                        v-model="paymentDetails.cardNumber"
                        placeholder="1234 5678 9012 3456"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        @input="formatCardNumber"
                    />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="expiryDate" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                        <input
                        type="text"
                        id="expiryDate"
                        v-model="paymentDetails.expiryDate"
                        placeholder="MM/YY"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        @input="formatExpiryDate"
                        />
                    </div>
                    <div>
                        <label for="cvv" class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                        <input
                        type="text"
                        id="cvv"
                        v-model="paymentDetails.cvv"
                        placeholder="123"
                        maxlength="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        />
                    </div>
                    </div>
                </div>

                <!-- Mobile Money Form -->
                <div v-if="isMobilePayment" class="space-y-4">
                    <div>
                    <label for="phoneNumber" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input
                        type="tel"
                        id="phoneNumber"
                        v-model="paymentDetails.phoneNumber"
                        placeholder="+263 7X XXX XXXX"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    />
                    </div>
                </div>
                </div>

                <!-- Step 3: Confirmation -->
                <div v-else-if="currentStep === 2" key="step3" class="space-y-4">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Confirm Payment</h4>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method</span>
                        <div class="flex items-center">
                        <img :src="selectedMethodIconUrl" :alt="selectedMethodName" width="90"  />
                        <!-- <div class="w-6 h-6 mr-2">
                            <component :is="selectedMethodIcon" v-if="selectedMethodComponent" class="h-5 w-5" />
                            <img v-else :src="selectedMethodIconUrl" :alt="selectedMethodName" class="h-5 w-auto object-contain" />
                        </div>
                        <span class="font-medium">{{ selectedMethodName }}</span> -->
                        </div>
                    </div>

                    <div v-if="isCardPayment" class="flex justify-between">
                        <span class="text-gray-600">Card Number</span>
                        <span class="font-medium">•••• •••• •••• {{ paymentDetails.cardNumber.slice(-4) }}</span>
                    </div>

                    <div v-if="isMobilePayment" class="flex justify-between">
                        <span class="text-gray-600">Phone Number</span>
                        <span class="font-medium">{{ paymentDetails.phoneNumber }}</span>
                    </div>
                    </div>
                </div>
                </div>
            </transition>
            </div>

            <!-- Navigation Buttons -->
            <div class="p-6 bg-gray-50 flex justify-between">
            <button
                v-if="currentStep > 0"
                @click="prevStep"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
                Back
            </button>
            <div v-else class="w-20"></div>

            <button
                v-if="currentStep < steps.length - 1"
                @click="nextStep"
                class="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                :disabled="!canProceed"
                :class="{'opacity-50 cursor-not-allowed': !canProceed}"
            >
                Next
            </button>
            <button
                v-else
                @click="confirmPayment"
                class="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
                Pay Now
            </button>
            </div>
        </div>

        <!-- Payment Processing View -->
        <div v-else >
            <div class="flex flex-col items-center justify-center py-8">
            <!-- Payment Method Logo -->
            <!-- <img
                :src="selectedMethodIconUrl"
                :alt="selectedMethodName"
                class="h-10 w-auto object-contain"
                /> -->
                <div v-if="selectedMethod === 'innbuck'">
                    <vue-qrcode
                    :value="qrCode"
                    :color=colors
                    :width=200
                    type="image/png"
                    />
                    <h3 class="text-center text-xl font-bold text-gray-800 mb-2">{{code}}</h3>
                </div>

            <!-- Countdown Timer -->
            <div class="relative w-48 h-48 mb-6">
                <!-- Circular Progress -->
                <svg class="w-full h-full" viewBox="0 0 100 100">
                <!-- Background Circle -->
                <circle
                    cx="50" cy="50" r="45"
                    fill="transparent"
                    stroke="#e5e7eb"
                    stroke-width="8"
                ></circle>

                <!-- Progress Circle -->
                <circle
                    cx="50" cy="50" r="45"
                    fill="transparent"
                    stroke="var(--color-primary)"
                    stroke-width="8"
                    stroke-linecap="round"
                    stroke-dasharray="282.7"
                    :stroke-dashoffset="282.7 * (1 - remainingTime / countdownTime)"
                    transform="rotate(-90 50 50)"
                    class="transition-all duration-1000 ease-linear"
                ></circle>
                </svg>

                <!-- Time Display -->
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-4xl font-bold text-gray-800">{{ formattedTime }}</span>
                <span class="text-sm text-gray-500 mt-1">remaining</span>
                </div>


            </div>

            <!-- Status Text -->
            <!-- <h3 class="text-xl font-bold text-gray-800 mb-2">Processing Payment</h3> -->
            <p class="text-gray-600 text-center mb-6 max-w-xs">
                <span v-if="selectedMethod === 'innbuck'">
                    Check your <strong class="font-bold">InnBucks</strong> app for confirmation prompt.
                </span>
                <span v-else>
                    Check your <strong class="font-bold">EcoCash</strong> phone for the payment prompt.
                </span>
            </p>

            <!-- Cancel Button -->
            <button
                @click="cancelPaymentProcess"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors focus:outline-none"
            >
                Cancel Payment
            </button>
            </div>
        </div>


         <!-- Footer -->
      <p class="text-center text-gray-400 text-xs ">
        Powered by Norah Payment Gateway
      </p>
      </div>
    </div>
  </template>

  <script>
  import axios from 'axios';
  import Loader from "./Loader.vue";
import VueQrcode from 'vue-qrcode';
export default {
    components: {
    VueQrcode,
    Loader
  },
    data() {
        return {
            colors: {
                dark: '#000000',
                light: '#ffffff',

            },
            code: '',
            qrCode: '',
            isLoading: false,
            token: null, // Token from the URL
            type: null, // Type from the URL
            isProcessing: false, // Tracks whether the payment is being processed
            countdownTime: 0, // Total countdown time in seconds
            remainingTime: 0, // Remaining time in seconds
            countdownInterval: null,
            message: '',
            error: '',
            tokenData: null,
            currentStep: 0,
            merchant: {
                name: '',
                description: '',
                email: '',
                website: '',
                logo: ''
            },
            payment: {
                amount: 0,
                charge: 0,
                total: 0,
                currency: ''
            },
            steps: [
                { name: 'Method' },
                { name: 'Details' },
                { name: 'Confirm' }
            ],
            paymentMethods: [
                {
                    id: 'visa',
                    name: 'VISA',
                    iconUrl: 'assets/visa.png',
                    component: false,
                    type: 'card'
                },
                {
                    id: 'mastercard',
                    name: 'MASTERCARD',
                    iconUrl: 'assets/mastercard.png',
                    component: false,
                    type: 'card'
                },
                {
                    id: 'zimswitch',
                    name: 'ZIMSWITCH',
                    iconUrl: 'assets/zimswitch.png',
                    component: false,
                    type: 'card'
                },
                {
                    id: 'innbuck',
                    name: 'InnBucks',
                    iconUrl: 'assets/innbucks.png',
                    component: false,
                    type: 'mobile'
                },
                {
                    id: 'ecocash',
                    name: 'EcoCash',
                    iconUrl: 'assets/ecocash.png',
                    component: false,
                    type: 'mobile'
                }
            ],
            selectedMethod: '',
            paymentDetails: {
                cardNumber: '',
                expiryDate: '',
                cvv: '',
                phoneNumber: ''
            }
        };
    },
    computed: {
        formattedTime() {
            const minutes = Math.floor(this.remainingTime / 60);
            const seconds = this.remainingTime % 60;
            return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
        },
        selectedPaymentType() {
            const method = this.paymentMethods.find(m => m.id === this.selectedMethod);
            return method ? method.type : null;
        },
        isCardPayment() {
            return this.selectedPaymentType === 'card';
        },
        isMobilePayment() {
            return this.selectedPaymentType === 'mobile';
        },
        selectedMethodDetails() {
            return this.paymentMethods.find(m => m.id === this.selectedMethod) || {};
        },
        selectedMethodName() {
            return this.selectedMethodDetails.name || '';
        },
        selectedMethodIcon() {
            return this.selectedMethodDetails.icon || null;
        },
        selectedMethodIconUrl() {
            return this.selectedMethodDetails.iconUrl || '';
        },
        selectedMethodComponent() {
            return this.selectedMethodDetails.component || false;
        },
        isFormValid() {
            if (this.isCardPayment) {
                return (
                    this.paymentDetails.cardNumber.replace(/\s/g, '').length >= 16 &&
                    this.paymentDetails.expiryDate.length === 5 &&
                    this.paymentDetails.cvv.length >= 3
                );
            } else if (this.isMobilePayment) {
                return this.paymentDetails.phoneNumber.length >= 10;
            }
            return false;
        },
        canProceed() {
            if (this.currentStep === 0) {
                return !!this.selectedMethod;
            } else if (this.currentStep === 1) {
                return this.isFormValid;
            }
            return true;
        }
    },
    async created() {
        const token = this.$route.query.token;
        const type = this.$route.query.type;

        if (!token || !type) {
            this.message = 'Invalid request. Token or type is missing.';
            return;
        }

        try {
            // Call the backend API to validate and decode the token
            const response = await axios.post('/api/v1/validate-token', { token, type });
            // this.message = response.data.message;

            // Map the token data to our component state
            this.tokenData = response.data.data;
            // console.log('Token Data:', this.tokenData);
            if (this.tokenData) {

                this.payment = {
                    amount: parseFloat(this.tokenData.amount) || 0,
                    charge: parseFloat(this.tokenData.charge) || 0,
                    total: parseFloat(this.tokenData.totalAmount) || 0,
                    currency: this.tokenData.currency || 'USD'
                };
            }
        } catch (error) {
            this.message = error.response?.data?.message || 'An error occurred while validating the token.';
            console.log(error);
        }
    },
    methods: {
        formatAmount(amount) {
            if (amount == null || isNaN(amount)) {
                return '0.00'; // Return a default value if the amount is invalid
            }
            return parseFloat(amount).toFixed(2);
        },
        formatCardNumber() {
            let value = this.paymentDetails.cardNumber.replace(/\s/g, '');
            if (value.length > 16) value = value.slice(0, 16);

            // Add spaces after every 4 digits
            const parts = [];
            for (let i = 0; i < value.length; i += 4) {
                parts.push(value.slice(i, i + 4));
            }

            this.paymentDetails.cardNumber = parts.join(' ');
        },
        formatExpiryDate() {
            let value = this.paymentDetails.expiryDate.replace(/\D/g, '');

            if (value.length > 0) {
                // Ensure month is between 01-12
                let month = value.slice(0, 2);
                if (month.length === 1) {
                    if (parseInt(month) > 1) {
                        month = '0' + month;
                    }
                } else {
                    if (parseInt(month) > 12) {
                        month = '12';
                    } else if (parseInt(month) === 0) {
                        month = '01';
                    }
                }

                // Format as MM/YY
                if (value.length > 2) {
                    this.paymentDetails.expiryDate = `${month}/${value.slice(2, 4)}`;
                } else {
                    this.paymentDetails.expiryDate = month;
                }
            }
        },
        selectPaymentMethod(methodId) {
            this.selectedMethod = methodId;
            // Reset form fields when changing payment method
            this.paymentDetails = {
                cardNumber: '',
                expiryDate: '',
                cvv: '',
                phoneNumber: ''
            };

            // Automatically advance to the next step after selecting a payment method
            this.nextStep();
        },
        nextStep() {
            if (this.currentStep < this.steps.length - 1 && this.canProceed) {
                this.currentStep++;
            }
        },
        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
            }
        },
        async confirmPayment() {
        this.isLoading = true;


        const requestData = {
            paymentMethod: this.selectedMethodName.toUpperCase(),
            amount: this.payment.amount,
            charge: this.payment.charge,
            total: this.payment.total,
            currency: this.payment.currency,
            phoneNumber: this.paymentDetails.phoneNumber,
            cardNumber: this.paymentDetails.cardNumber,
            expiryDate: this.paymentDetails.expiryDate,
            cvv: this.paymentDetails.cvv,
            user: this.tokenData.user,
            narration: this.selectedMethodName.toUpperCase() + ' Payment',
            type: 'PAYMENT',
        };


        try {
            const response = await axios.post('/api/v1/transactions/confirmation', requestData);
            this.isLoading = false;
            if (response.data.success) {

                this.confirmPaymentSuccess(response.data.data);

            } else {
                this.$swal.fire(
                    "Payment Failed",
                   response.data.message || "Payment confirmation failed.",
                    "error"
                );
                // alert('Payment confirmation failed: ' + response.data.message);
            }
        } catch (error) {
            this.isLoading = false;
            this.$swal.fire(
                    "Payment Failed",
                   'Error confirming payment:', error.response?.data || error.message,
                    "error"
                );
            console.error('Error confirming payment:', error.response?.data || error.message);
            // alert('An error occurred while confirming the payment.');
        }
    },
        confirmPaymentSuccess(data) {
            console.log('Response:', data);
            const paymentType = this.selectedPaymentType;
            this.code = data.code;
            this.qrCode = data.code;

            if (paymentType === "card") {
            // For card payments, confirm the payment with card details
            const details = `${this.selectedMethodName} card ending in ${this.paymentDetails.cardNumber.slice(-4)}`;
            alert(`Payment of ${this.payment.currency} ${this.formatAmount(this.payment.total)} confirmed with ${details}!`);
            } else if (paymentType === "mobile") {
            // For mobile payments (EcoCash or InnBucks), start a countdown timer
            this.isProcessing = true;

            // Set countdown time based on the selected method
            if (this.selectedMethod === "innbuck") {
                this.countdownTime = 10 * 60; // 10 minutes in seconds
            } else if (this.selectedMethod === "ecocash") {
                this.countdownTime = 1 * 60; // 1 minute in seconds
            }

            // Initialize the remaining time to the full countdown time
            this.remainingTime = this.countdownTime;

            // Start the countdown timer
            this.countdownInterval = setInterval(() => {
                if (this.remainingTime > 0) {
                this.remainingTime--;

                // Simulate a successful payment randomly (for demo purposes)
                if (Math.random() < 0.001) { // 0.1% chance each second
                    clearInterval(this.countdownInterval);
                    alert("Payment successful! Thank you for your purchase.");
                    this.isProcessing = false;
                }
                } else {
                // Time's up, clear the interval
                clearInterval(this.countdownInterval);
                alert("Payment session timed out. Please try again.");
                this.isProcessing = false;
                }
            }, 1000); // Update every second
            }
        },

        cancelPaymentProcess() {
            clearInterval(this.countdownInterval); // Stop the countdown timer
            this.isProcessing = false; // Reset the processing state
            alert("Payment process canceled.");
        },
    }
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

  .bg-primary {
    background-color: var(--color-primary);
  }

  .text-primary {
    color: var(--color-primary);
  }

  .border-primary {
    border-color: var(--color-primary);
  }

  .bg-primary\/5 {
    background-color: rgba(79, 70, 229, 0.05);
  }

  .bg-primary\/90 {
    background-color: rgba(79, 70, 229, 0.9);
  }

  .focus\:ring-primary:focus {
    --tw-ring-color: var(--color-primary);
  }

  /* Transitions */
  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
  }

  .fade-enter-from,
  .fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
  }
  </style>
