



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
                                height: method.id === 'visa_master' ? '16px' : method.id === 'omari' ? '32px' : method.id === 'zimswitch' ? '32px' : method.id === 'innbuck' ? '16px' : method.id === 'ecocash' ? '18px' : '50px',
                                width: method.id === 'visa_master' ? 'auto' : method.id === 'omari' ? 'auto' : method.id === 'zimswitch' ? 'auto' : method.id === 'innbuck' ? 'auto' : method.id === 'ecocash' ? 'auto' : '50px'
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

                <!-- Special message for Zimswitch and VISA/MasterCard -->
                <div v-if="selectedMethod === 'zimswitch' || selectedMethod === 'visa_master'" class="space-y-4">
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-center">
                        <div class="flex items-center justify-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-semibold text-blue-700">Secure Payment</span>
                        </div>
                        <p class="text-blue-700 text-sm">
                            <span v-if="selectedMethod === 'zimswitch'">Your Zimswitch card details will be collected securely on the next screen by our payment partner.</span>
                            <span v-if="selectedMethod === 'visa_master'">Your VISA/MasterCard details will be collected securely on the next screen by our payment partner.</span>
                        </p>
                        <p class="mt-2 text-blue-700 text-sm font-medium">Click "Next" then "Pay Now" to proceed to the secure payment page.</p>
                    </div>
                </div>

                <!-- Card Payment Form for other card methods (excluding Zimswitch and VISA/MasterCard) -->
                <div v-else-if="isCardPayment && selectedMethod !== 'zimswitch' && selectedMethod !== 'visa_master'" class="space-y-4">
                    <div>
                    <label for="cardNumber" class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                    <div class="relative">
                        <input
                            type="text"
                            id="cardNumber"
                            v-model="paymentDetails.cardNumber"
                            placeholder="1234 5678 9012 3456"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            @input="formatCardNumber"
                        />
                        <div v-if="detectedCardType" class="absolute right-3 top-1/2 transform -translate-y-1/2 flex items-center">
                            <span class="text-sm font-medium text-gray-600">{{ detectedCardIcon }}</span>
                        </div>
                    </div>
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

                    <div v-if="isCardPayment && selectedMethod !== 'zimswitch' && selectedMethod !== 'visa_master'" class="flex justify-between">
                        <span class="text-gray-600">Card Number</span>
                        <span class="font-medium">•••• •••• •••• {{ paymentDetails.cardNumber.slice(-4) }}</span>
                    </div>

                    <div v-if="selectedMethod === 'zimswitch' || selectedMethod === 'visa_master'" class="flex justify-between">
                        <span class="text-gray-600">Card Details</span>
                        <span class="font-medium text-blue-600">Will be collected securely on next screen</span>
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

                <!-- OTP Input for Omari -->
                <div v-if="selectedMethod === 'omari' && !otpSubmitted" class="my-4 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-center text-lg font-bold text-gray-800 mb-4">Enter OTP Sent to Your Phone</h4>
                    <div class="flex flex-col items-center space-y-4">
                        <input
                            type="text"
                            v-model="otpCode"
                            placeholder="Enter OTP"
                            class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md text-center focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            maxlength="6"
                        />
                        <button
                            @click="submitOtp"
                            class="w-full max-w-xs px-4 py-2 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg transition-colors focus:outline-none"
                            :disabled="!otpCode || otpCode.length < 4"
                            :class="{'opacity-50 cursor-not-allowed': !otpCode || otpCode.length < 4}"
                        >
                            Verify OTP
                        </button>
                    </div>
                </div>

            <!-- Countdown Timer - Only show when not waiting for OTP input (for Omari) -->
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
                <span v-if="selectedMethod === 'ecocash'">
                    Check your <strong class="font-bold">EcoCash</strong> phone for the payment confirmation OTP.
                </span>
                <span v-if="selectedMethod === 'omari'">
                    Check your <strong class="font-bold">OMARI</strong> phone for the payment confirmation OTP.
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
            pollingInterval: null,
            pollAttempts: 0,
            maxPollAttempts: 60, // 5 minutes at 5s intervals or 30 minutes at 30s intervals
            colors: {
                dark: '#000000',
                light: '#ffffff',

            },
            trace : '',
            returnUrl: '',
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
            otpCode: '',
            otpSubmitted: false,
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
                    id: 'visa_master',
                    name: 'VISA_MASTER',
                    iconUrl: 'assets/visa_master.png',
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
                    id: 'omari',
                    name: 'OMARI',
                    iconUrl: 'assets/omari.png',
                    component: false,
                    type: 'mobile'
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
                nameOnCard: '',
                phoneNumber: ''
            },
            detectedCardType: '',
            detectedCardIcon: '',
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
            // For Zimswitch and VISA/MasterCard, we don't need to validate card details
            // as they'll be collected on the hosted payment pages
            if (this.selectedMethod === 'zimswitch' || this.selectedMethod === 'visa_master') {
                return true;
            } else if (this.isCardPayment) {
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

            // Detect card type in real-time
            this.detectCardType(value);
        },
        detectCardType(cardNumber) {
            // Remove spaces and non-numeric characters
            cardNumber = cardNumber.replace(/\D/g, '');

            // Check for common card types based on patterns
            let cardType = '';
            let cardIcon = '';

            // Visa cards start with 4
            if (/^4/.test(cardNumber)) {
                cardType = 'Visa';
                cardIcon = '💳 Visa';
            }
            // Mastercard starts with 51-55 or 2221-2720
            else if (/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[0-1]|2720)/.test(cardNumber)) {
                cardType = 'MasterCard';
                cardIcon = '💳 MasterCard';
            }
            // American Express starts with 34 or 37
            else if (/^3[47]/.test(cardNumber)) {
                cardType = 'American Express';
                cardIcon = '💳 AMEX';
            }
            // Discover starts with 6011, 622126-622925, 644-649, 65
            else if (/^(6011|622(12[6-9]|1[3-9]|[2-8]|9[0-1][0-9]|92[0-5])|64[4-9]|65)/.test(cardNumber)) {
                cardType = 'Discover';
                cardIcon = '💳 Discover';
            }

            this.detectedCardType = cardType;
            this.detectedCardIcon = cardIcon;
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


        // Create base request data
        let requestData = {
            paymentMethod: this.selectedMethodName.toUpperCase(),
            amount: this.payment.amount,
            charge: this.payment.charge,
            total: this.payment.total,
            currency: this.payment.currency,
            user: this.tokenData.user,
            narration: this.selectedMethodName.toUpperCase() + ' Payment',
            type: 'PAYMENT',
        };

        // For Zimswitch and VISA/MasterCard, we don't include card details as they'll be collected on the hosted payment page
        if (this.selectedMethod !== 'zimswitch' && this.selectedMethod !== 'visa_master') {
            // Only add card or phone details for payments that don't use hosted payment pages
            if (this.isCardPayment) {
                requestData = {
                    ...requestData,
                    cardNumber: this.paymentDetails.cardNumber,
                    expiryDate: this.paymentDetails.expiryDate,
                    cvv: this.paymentDetails.cvv,
                };
            } else if (this.isMobilePayment) {
                requestData = {
                    ...requestData,
                    phoneNumber: this.paymentDetails.phoneNumber,
                };
            }
        }


        try {
            const response = await axios.post('/api/v1/transactions/confirmation', requestData);
            this.isLoading = false;

            if (response.data.success) {
                this.trace = response.data.trace;
                this.returnUrl = response.data.returnUrl;

                // Check for errors in the response first
                if (!response.data.success || (response.data.data && response.data.data.success === false)) {
                    this.isLoading = false;
                    let errorMessage = '';

                    // Handle iVeri specific errors that may be nested in the response
                    if (this.selectedMethod === 'visa_master' && response.data.data) {
                        const responseData = response.data.data;

                        if (responseData.responseData && responseData.responseData.Result) {
                            // Extract the detailed error message from the iVeri response
                            errorMessage = `${responseData.message}: ${responseData.responseData.Result.Description} (Code: ${responseData.responseData.Result.Code})`;
                        } else {
                            errorMessage = responseData.message || 'Payment processing failed';
                        }
                    } else {
                        // Default error handling for other payment methods
                        errorMessage = response.data.message ||
                                      (response.data.data ? response.data.data.message : 'Payment failed');
                    }
                    this.$swal.fire(
                        "Payment Failed",
                        errorMessage || "Payment confirmation failed.",
                        "error"
                    );

                    // Also update the UI to show the error
                    this.errorMessage = errorMessage;
                    this.hasError = true;
                    return;
                }

                // Check if this is an Omari payment that requires OTP immediately
                if (this.selectedMethod === "omari" && response.data.requiresOtp) {
                    // Skip the normal QR code display and show OTP input immediately
                    this.isProcessing = true;
                    this.countdownTime = 5 * 60; // 5 minutes in seconds
                    this.remainingTime = this.countdownTime;

                    // Start countdown timer
                    this.startCountdown();

                    // Start polling
                    this.startPolling(response.data.trace);
                } else if ((this.selectedMethod === "zimswitch" || this.selectedMethod === "visa_master") &&
                          (response.data.checkoutId || response.data.redirectUrl)) {
                    // For Zimswitch and VISA/Master payments that use hosted payment pages,
                    // we'll use a redirect approach to the payment processor

                    // Show loading state for either payment method
                    this.isLoading = true;
                    this.message = 'Preparing secure payment form...';

                    // Check if we have a direct redirect URL (for iVeri)
                    if (this.selectedMethod === "visa_master" && response.data.redirectUrl) {
                        // Create a full-page payment overlay similar to Zimswitch but for iVeri
                        this.createPaymentOverlay(
                            'VISA/MasterCard Payment',
                            this.payment.currency,
                            this.formatAmount(this.payment.total),
                            response.data.redirectUrl
                        );
                        return;
                    }
                    // Handle Zimswitch payment
                    else if (this.selectedMethod === "zimswitch" && response.data.checkoutId) {
                        const baseUrl = response.data.paymentUrl;
                        const checkoutId = response.data.checkoutId;

                        // Create a payment form for Zimswitch with EFTPay widget
                        this.createZimswitchPaymentForm(baseUrl, checkoutId);
                        return;
                    }

                    // [Code removed: This block is no longer needed as it's been refactored into helper methods]
                } else {
                    // Normal flow for other payment methods
                    this.confirmPaymentSuccess(response.data.data, response.data.trace);
                }

            } else {
                this.$swal.fire(
                    "Payment Failed",
                   response.data.message || "Payment confirmation failed.",
                    "error"
                );
                // alert('Payment confirmation failed: ' + response.data.message);
            }
        } catch (error) {
            console.log(error);
            this.isLoading = false;
            this.$swal.fire(
                    "Payment Failed",
                   'Error confirming payment:', error.response?.data || error.message || response.data.data.message,
                    "error"
                );
            console.error('Error confirming payment:', error.response?.data || error.message || response.data.data.message);
            // alert('An error occurred while confirming the payment.');
        }
    },
    async confirmPaymentSuccess(data, trace) {
            this.code = data.code;
            this.qrCode = data.code;
            this.trace = trace;

            if (this.selectedPaymentType === "mobile") {
                this.isProcessing = true;

                // Set countdown time based on the selected method
                if (this.selectedMethod === "innbuck") {
                    this.countdownTime = 10 * 60; // 10 minutes in seconds
                } else if (this.selectedMethod === "ecocash") {
                    this.countdownTime = 1 * 60; // 1 minute in seconds
                } else if (this.selectedMethod === "omari") {
                    this.countdownTime = 5 * 60; // 5 minutes in seconds
                }

                this.remainingTime = this.countdownTime;

                // Start countdown and polling
                this.startCountdown();
                this.startPolling(trace);
            }
        },

        startCountdown() {
            // Start countdown timer
            this.countdownInterval = setInterval(() => {
                if (this.remainingTime > 0) {
                    this.remainingTime--;
                } else {
                    clearInterval(this.countdownInterval);
                    this.stopPolling();
                    this.$swal.fire(
                        "Timeout",
                        "Payment session timed out. Please try again.",
                        "error"
                    );
                    this.isProcessing = false;
                }
            }, 1000);
        },

        startPolling(trace) {
            this.pollAttempts = 0;
            this.pollingInterval = setInterval(() => {
                this.checkTransactionStatus(trace);
            }, this.getPollingInterval()); // Use a method to determine interval based on payment method
        },

        stopPolling() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },

        async checkTransactionStatus(trace) {
            if (this.pollAttempts >= this.maxPollAttempts) {
                this.stopPolling();
                this.$swal.fire(
                    "Timeout",
                    "Maximum polling attempts reached. Please check your payment status later.",
                    "error"
                );
                this.isProcessing = false;
                window.location.href = this.returnUrl;
                return;
            }

            this.pollAttempts++;

            try {
                const response = await axios.post('/api/v1/transactions/status', {
                    trace: this.trace
                });

                if (response.data.status === 'COMPLETED') {
                    this.stopPolling();
                    clearInterval(this.countdownInterval);
                    this.$swal.fire(
                        "Success",
                        "Your transaction was successfully completed!",
                        "success"
                    ).then(() => {
                        window.location.href = this.returnUrl;
                    });
                    this.isProcessing = false;
                } else if (response.data.status === 'FAILED' || response.data.status === 'CANCELLED') {
                    this.stopPolling();
                    clearInterval(this.countdownInterval);
                    this.$swal.fire(
                        "Error",
                        response.data.responseMessage || "Transaction failed.",
                        "error"
                    );
                    this.isProcessing = false;
                    window.location.href = this.returnUrl;
                } else if (response.data.status === 'PENDING') {
                    // Check if we need to collect OTP (Omari payment)
                    if (response.data.requiresOtp && this.selectedMethod === 'omari' && !this.otpSubmitted) {
                        // We'll show the OTP input field, but continue polling
                        // The OTP will be submitted via the submitOtp method
                    }
                    // Otherwise continue polling
                }
            } catch (error) {
                console.error('Error checking transaction status:', error);
                this.$swal.fire(
                    "Error",
                    "An error occurred while checking the transaction status.",
                    "error"
                );
                window.location.href = this.returnUrl;
                // Continue polling even if there's an error (network issues, etc.)
            }
        },

        cancelPaymentProcess() {
            this.$swal.fire({
                title: "Are you sure?",
                text: "You are about to cancel the payment process.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, cancel it!",
                cancelButtonText: "No, keep it"
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await axios.post('/api/v1/transactions/cancel', {
                            trace: this.trace
                        });

                        this.stopPolling();
                        clearInterval(this.countdownInterval);
                        this.isProcessing = false;

                        this.$swal.fire(
                            "Cancelled",
                            "Payment process has been cancelled.",
                            "success"
                        );
                        window.location.href = this.returnUrl;
                    } catch (error) {
                        this.$swal.fire(
                            "Error",
                            "Failed to cancel transaction: " + (error.response?.data?.message || error.message),
                            "error"
                        );
                        window.location.href = this.returnUrl;
                    }
                }
            });
        },

        getPollingInterval() {
            // Return polling interval based on payment method
            if (this.selectedMethod === "innbuck") {
                return 30000; // 30s for InnBucks
            } else if (this.selectedMethod === "omari") {
                return 10000; // 10s for Omari
            } else {
                return 5000; // 5s default
            }
        },

        submitOtp() {
            if (!this.otpCode || this.otpCode.length < 4) {
                return;
            }

            this.otpSubmitted = true;

            // Submit OTP to dedicated Omari OTP endpoint
            axios.post('/api/v1/transactions/omari-otp', {
                trace: this.trace,
                otp: this.otpCode
            }).then(response => {
                if (response.data.success) {
                    console.log('OTP submitted successfully:', response.data);

                    // Start countdown timer after OTP is submitted and verified
                    this.startCountdown();

                    // Start polling for status updates
                    this.startPolling(this.trace);

                    // Show success message
                    this.$swal.fire({
                        title: "OTP Verified",
                        text: "Payment is being processed",
                        icon: "success",
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    this.otpSubmitted = false; // Allow retry

                    // Display specific error message for known error codes
                    let errorMessage = response.data.message || "Failed to verify OTP. Please try again.";
                    let errorTitle = "Error";

                    // Handle specific error codes
                    if (response.data.responseCode === "051") {
                        errorTitle = "Insufficient Funds";
                        errorMessage = "Your payment could not be processed due to insufficient funds.";
                    }

                    this.$swal.fire(
                        errorTitle,
                        errorMessage,
                        "error"
                    ).then(() => {
                        // If it's a payment failure (not just an OTP validation error), redirect to return URL
                        if (response.data.responseCode) {
                            window.location.href = this.returnUrl;
                        }
                    });
                }
            }).catch(error => {
                console.error('Error submitting OTP:', error);
                this.otpSubmitted = false; // Allow retry
                this.$swal.fire(
                    "Error",
                    error.response?.data?.message || "Failed to verify OTP. Please try again.",
                    "error"
                );
            });
        },

        /**
         * Creates a payment overlay for iVeri redirects
         * @param {string} title - The title to display in the payment overlay
         * @param {string} currency - The currency code (e.g., USD)
         * @param {string} amount - The formatted amount
         * @param {string} redirectUrl - The URL to redirect to for payment
         */
        createPaymentOverlay(title, currency, amount, redirectUrl) {
            // Create base overlay
            const overlay = this.createBaseOverlay();

            // Create header with title and amount
            const header = this.createOverlayHeader(title, `${currency} ${amount}`);
            overlay.appendChild(header);

            // Create the iframe container
            const iframeContainer = document.createElement('div');
            iframeContainer.style.width = '100%';
            iframeContainer.style.maxWidth = '500px';
            iframeContainer.style.height = '400px';
            iframeContainer.style.backgroundColor = '#fff';
            iframeContainer.style.borderRadius = '14px';
            iframeContainer.style.overflow = 'hidden';
            iframeContainer.style.boxShadow = '0 10px 25px rgba(0,0,0,0.3)';

            // Create and add the iframe
            const iframe = document.createElement('iframe');
            iframe.src = redirectUrl;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframeContainer.appendChild(iframe);

            overlay.appendChild(iframeContainer);

            // Add a cancel button
            const cancelButton = this.createCancelButton(overlay);
            overlay.appendChild(cancelButton);

            // Add the overlay to the body
            document.body.appendChild(overlay);

            this.isLoading = false;
            console.log('Displaying iVeri payment form with redirect URL:', redirectUrl);
        },

        /**
         * Creates a payment form for Zimswitch using EFTPay
         * @param {string} baseUrl - The base URL for the EFTPay API
         * @param {string} checkoutId - The checkout ID for the payment
         */
        createZimswitchPaymentForm(baseUrl, checkoutId) {
            // Create base overlay
            const overlay = this.createBaseOverlay();

            // Create header with title and amount
            const header = this.createOverlayHeader('Zimswitch Payment', `${this.payment.currency} ${this.formatAmount(this.payment.total)}`);
            overlay.appendChild(header);

            // Create the widget container
            const widgetContainer = document.createElement('div');
            widgetContainer.style.width = '100%';
            widgetContainer.style.maxWidth = '500px';
            widgetContainer.style.padding = '25px';
            widgetContainer.style.boxShadow = '0 10px 25px rgba(0,0,0,0.3), 0 0 1px rgba(255,255,255,0.1)';
            widgetContainer.style.borderRadius = '14px';
            widgetContainer.style.backgroundColor = '#fff';
            widgetContainer.style.border = '1px solid rgba(255,255,255,0.15)';
            widgetContainer.style.transform = 'translateY(0)';
            widgetContainer.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';

            // Add hover effects
            widgetContainer.onmouseover = () => {
                widgetContainer.style.transform = 'translateY(-2px)';
                widgetContainer.style.boxShadow = '0 14px 30px rgba(0,0,0,0.4), 0 0 1px rgba(255,255,255,0.15)';
            };
            widgetContainer.onmouseout = () => {
                widgetContainer.style.transform = 'translateY(0)';
                widgetContainer.style.boxShadow = '0 10px 25px rgba(0,0,0,0.3), 0 0 1px rgba(255,255,255,0.1)';
            };

            // Add form for the EFTPay widget
            const form = document.createElement('form');
            form.action = `${window.location.origin}/payment/callback`;
            form.className = 'paymentWidgets';
            form.setAttribute('data-brands', 'PRIVATE_LABEL');
            widgetContainer.appendChild(form);

            overlay.appendChild(widgetContainer);

            // Add a cancel button
            const cancelButton = this.createCancelButton(overlay);
            overlay.appendChild(cancelButton);

            // Add the overlay to the body
            document.body.appendChild(overlay);

            // Add the EFTPay script
            const script = document.createElement('script');
            script.src = `${baseUrl}/v1/paymentWidgets.js?checkoutId=${checkoutId}`;
            script.setAttribute('crossorigin', 'anonymous');
            document.head.appendChild(script);

            this.isLoading = false;
            console.log('Displaying EFTPay payment form with checkout ID:', checkoutId);
        },

        /**
         * Creates a base overlay for payment forms
         * @returns {HTMLDivElement} - The overlay element
         */
        createBaseOverlay() {
            const overlay = document.createElement('div');
            overlay.id = 'payment-overlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'linear-gradient(135deg, rgba(26, 32, 44, 0.96) 0%, rgba(17, 24, 39, 0.96) 100%)';
            overlay.style.backdropFilter = 'blur(10px)';
            overlay.style.webkitBackdropFilter = 'blur(10px)';
            overlay.style.zIndex = '9999';
            overlay.style.display = 'flex';
            overlay.style.flexDirection = 'column';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.padding = '20px';
            return overlay;
        },

        /**
         * Creates a header for the payment overlay
         * @param {string} title - The title to display
         * @param {string} subtitle - The subtitle to display (usually the amount)
         * @returns {HTMLDivElement} - The header element
         */
        createOverlayHeader(title, subtitle) {
            const header = document.createElement('div');
            header.style.marginBottom = '30px';
            header.style.textAlign = 'center';

            // Add heading
            const heading = document.createElement('h2');
            heading.textContent = title;
            heading.style.fontSize = '28px';
            heading.style.color = '#ffffff';
            heading.style.margin = '20px 0';
            heading.style.fontWeight = 'bold';
            header.appendChild(heading);

            // Add subheading
            const subheading = document.createElement('p');
            subheading.textContent = `Amount: ${subtitle}`;
            subheading.style.fontSize = '20px';
            subheading.style.color = '#ffffff';
            subheading.style.margin = '10px 0';
            header.appendChild(subheading);

            return header;
        },

        /**
         * Creates a cancel button for the payment overlay
         * @param {HTMLDivElement} overlay - The overlay to remove when canceled
         * @returns {HTMLButtonElement} - The cancel button
         */
        createCancelButton(overlay) {
            const cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancel Payment';
            cancelButton.style.marginTop = '30px';
            cancelButton.style.padding = '14px 28px';
            cancelButton.style.backgroundColor = 'rgba(220, 38, 38, 0.9)';
            cancelButton.style.color = 'white';
            cancelButton.style.border = 'none';
            cancelButton.style.borderRadius = '8px';
            cancelButton.style.cursor = 'pointer';
            cancelButton.style.fontSize = '16px';
            cancelButton.style.fontWeight = 'bold';
            cancelButton.style.boxShadow = '0 4px 12px rgba(220, 38, 38, 0.4)';
            cancelButton.style.transition = 'all 0.2s ease';

            // Add hover and active effects
            cancelButton.onmouseover = () => {
                cancelButton.style.backgroundColor = 'rgba(220, 38, 38, 1)';
                cancelButton.style.transform = 'translateY(-2px)';
                cancelButton.style.boxShadow = '0 6px 16px rgba(220, 38, 38, 0.5)';
            };
            cancelButton.onmouseout = () => {
                cancelButton.style.backgroundColor = 'rgba(220, 38, 38, 0.9)';
                cancelButton.style.transform = 'translateY(0)';
                cancelButton.style.boxShadow = '0 4px 12px rgba(220, 38, 38, 0.4)';
            };
            cancelButton.onmousedown = () => {
                cancelButton.style.transform = 'translateY(1px)';
                cancelButton.style.boxShadow = '0 2px 8px rgba(220, 38, 38, 0.4)';
            };
            cancelButton.onclick = () => {
                // Remove the overlay and return to app
                document.body.removeChild(overlay);
                this.isLoading = false;
            };

            return cancelButton;
        },

        beforeDestroy() {
            this.stopPolling();
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
            }
        }
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
