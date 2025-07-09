



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

                <!-- Special message for Zimswitch -->
                <div v-if="selectedMethod === 'zimswitch'" class="space-y-4">
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-center">
                        <div class="flex items-center justify-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-semibold text-blue-700">Secure Payment</span>
                        </div>
                        <p class="text-blue-700 text-sm">
                            Your Zimswitch card details will be collected securely on the next screen by our payment partner.
                        </p>
                        <p class="mt-2 text-blue-700 text-sm font-medium">Click "Next" then "Pay Now" to proceed to the secure payment page.</p>
                    </div>
                </div>

                <!-- Card Payment Form for card methods (excluding Zimswitch) -->
                <div v-else-if="isCardPayment && selectedMethod !== 'zimswitch'" class="space-y-4">
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

                    <div v-if="selectedMethod === 'zimswitch'" class="flex justify-between">
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
            // For Zimswitch, we don't need to validate card details
            // as they'll be collected on the hosted payment page
            if (this.selectedMethod === 'zimswitch') {
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

        // Save return URL to state for redirects
        this.returnUrl = this.tokenData.returnUrl || '/';

        // For Zimswitch, we don't include card details as they'll be collected on the hosted payment page
        // For VISA/MasterCard, we need to include card details for 3D Secure processing
        if (this.selectedMethod !== 'zimswitch') {
            // For VISA/MasterCard, we need to include card details for 3D Secure processing
            if (this.selectedMethod === "visa_master") {
                requestData = {
                    ...requestData,
                    cardNumber: this.paymentDetails.cardNumber,
                    expiryDate: this.paymentDetails.expiryDate,
                    cvv: this.paymentDetails.cvv,
                    nameOnCard: this.paymentDetails.nameOnCard
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
                            errorMessage = `${responseData.responseData.Result.Description} (Code: ${responseData.responseData.Result.Code})`;
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

                    // Handle iVeri payment
                    if (this.selectedMethod === "visa_master") {
                        // Save trace ID for later status checks
                        if (response.data.trace) {
                            this.trace = response.data.trace;
                        }

                        // Case 1: Direct redirect URL to 3D Secure
                        if (response.data.redirectUrl) {
                            // Create a payment form for iVeri with redirect URL
                            this.createPaymentOverlay(
                                'Card Payment Authentication',
                                this.payment.currency,
                                this.formatAmount(this.payment.total),
                                response.data.redirectUrl
                            );
                            return;
                        }

                        // Case 2: ACS form data for 3D Secure
                        if (response.data.acsUrl && response.data.acsPayload) {
                            // Create and submit an automatic form to the ACS URL
                            this.create3DSecureForm(
                                'Card Payment Authentication',
                                this.payment.currency,
                                this.formatAmount(this.payment.total),
                                response.data.acsUrl,
                                response.data.acsPayload
                            );
                            return;
                                                }                  // Handle Zimswitch payment - integrated in Vue.js
                    } else if (this.selectedMethod === "zimswitch" && response.data.integrateInVue) {
                        // Integrate Zimswitch payment directly in Vue.js component
                        this.integrateZimswitchPayment(response.data);
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
         * Opens a 3D Secure authentication popup window for iVeri payments
         * @param {string} title - The title to display in the payment overlay
         * @param {string} currency - The currency code (e.g., USD)
         * @param {string} amount - The formatted amount
         * @param {string} redirectUrl - The URL to open in the popup
         */
        createPaymentOverlay(title, currency, amount, redirectUrl) {
            // Create a notification overlay to inform user about popup
            const overlay = this.createBaseOverlay();

            // Create header with title and amount
            const header = this.createOverlayHeader(title, `${currency} ${amount}`);
            overlay.appendChild(header);

            // Create notification container
            const notificationContainer = document.createElement('div');
            notificationContainer.style.width = '90%';
            notificationContainer.style.maxWidth = '500px';
            notificationContainer.style.padding = '30px';
            notificationContainer.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
            notificationContainer.style.borderRadius = '12px';
            notificationContainer.style.textAlign = 'center';
            notificationContainer.style.margin = '20px 0';

            // Add notification text
            const notificationText = document.createElement('p');
            notificationText.innerHTML = 'We have opened a secure authentication window.<br>Please complete the verification process in the popup window.<br>If you don\'t see a popup, please check your browser\'s popup blocker.';
            notificationText.style.color = '#ffffff';
            notificationText.style.fontSize = '16px';
            notificationText.style.lineHeight = '1.6';
            notificationText.style.marginBottom = '20px';
            notificationContainer.appendChild(notificationText);

            // Add popup reminder icon
            const popupIcon = document.createElement('div');
            popupIcon.innerHTML = '⚠️';
            popupIcon.style.fontSize = '40px';
            popupIcon.style.marginBottom = '20px';
            notificationContainer.appendChild(popupIcon);

            // Add reopen button in case popup is blocked
            const reopenButton = document.createElement('button');
            reopenButton.textContent = 'Reopen Authentication Window';
            reopenButton.style.padding = '12px 24px';
            reopenButton.style.backgroundColor = 'rgba(79, 70, 229, 0.9)';
            reopenButton.style.color = 'white';
            reopenButton.style.border = 'none';
            reopenButton.style.borderRadius = '8px';
            reopenButton.style.fontWeight = 'bold';
            reopenButton.style.cursor = 'pointer';
            reopenButton.style.boxShadow = '0 4px 12px rgba(79, 70, 229, 0.4)';
            reopenButton.style.transition = 'all 0.2s ease';

            // Add hover and active effects
            reopenButton.onmouseover = () => {
                reopenButton.style.backgroundColor = 'rgba(79, 70, 229, 1)';
                reopenButton.style.transform = 'translateY(-2px)';
                reopenButton.style.boxShadow = '0 6px 16px rgba(79, 70, 229, 0.5)';
            };
            reopenButton.onmouseout = () => {
                reopenButton.style.backgroundColor = 'rgba(79, 70, 229, 0.9)';
                reopenButton.style.transform = 'translateY(0)';
                reopenButton.style.boxShadow = '0 4px 12px rgba(79, 70, 229, 0.4)';
            };
            reopenButton.onmousedown = () => {
                reopenButton.style.transform = 'translateY(1px)';
                reopenButton.style.boxShadow = '0 2px 8px rgba(79, 70, 229, 0.4)';
            };

            let popupWindow = null;
            // Function to open popup
            const openPopup = () => {
                // Close existing popup if open
                if (popupWindow && !popupWindow.closed) {
                    popupWindow.close();
                }
                // Open new popup window
                const width = 450;
                const height = 600;
                const left = (window.innerWidth - width) / 2 + window.screenX;
                const top = (window.innerHeight - height) / 2 + window.screenY;
                popupWindow = window.open(
                    redirectUrl,
                    '3DSecurePopup',
                    `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,status=yes`
                );

                // Focus the popup
                if (popupWindow) {
                    popupWindow.focus();

                    // Check if popup was blocked
                    setTimeout(() => {
                        if (!popupWindow || popupWindow.closed || popupWindow.closed === undefined) {
                            notificationText.innerHTML = '<span style="color:#f87171">Popup was blocked!</span><br>Please click the button below to open the authentication window.';
                        }
                    }, 1000);
                }
            };

            // Attach open popup handler to button
            reopenButton.onclick = openPopup;
            notificationContainer.appendChild(reopenButton);

            overlay.appendChild(notificationContainer);

            // Add a cancel button
            const cancelButton = this.createCancelButton(overlay);
            overlay.appendChild(cancelButton);

            // Add the overlay to the body
            document.body.appendChild(overlay);

            // Open the popup window immediately
            openPopup();

            this.isLoading = false;
            console.log('Opening 3D Secure popup window with URL:', redirectUrl);
        },

        /**
         * Creates and submits a form for 3D Secure ACS authentication using popup method
         * @param {string} title - The title to display in the notification overlay
         * @param {string} currency - The currency code (e.g., USD)
         * @param {string} amount - The formatted amount
         * @param {string} acsUrl - The ACS URL to submit the form to
         * @param {string} acsPayload - The encoded payload data to submit
         */
        create3DSecureForm(title, currency, amount, acsUrl, acsPayload) {
            // Create notification overlay similar to the payment overlay
            const overlay = this.createBaseOverlay();

            // Create header with title and amount
            const header = this.createOverlayHeader(title, `${currency} ${amount}`);
            overlay.appendChild(header);

            // Create notification container
            const notificationContainer = document.createElement('div');
            notificationContainer.style.width = '90%';
            notificationContainer.style.maxWidth = '500px';
            notificationContainer.style.padding = '30px';
            notificationContainer.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
            notificationContainer.style.borderRadius = '12px';
            notificationContainer.style.textAlign = 'center';
            notificationContainer.style.margin = '20px 0';

            // Initial loading state
            const loadingText = document.createElement('p');
            loadingText.innerHTML = 'Preparing secure authentication window...<br>Please wait a moment.';
            loadingText.style.color = '#ffffff';
            loadingText.style.fontSize = '16px';
            loadingText.style.lineHeight = '1.6';
            loadingText.style.marginBottom = '20px';
            notificationContainer.appendChild(loadingText);

            // Add spinner
            const spinner = document.createElement('div');
            spinner.style.border = '5px solid rgba(255, 255, 255, 0.3)';
            spinner.style.borderTop = '5px solid #ffffff';
            spinner.style.borderRadius = '50%';
            spinner.style.width = '50px';
            spinner.style.height = '50px';
            spinner.style.animation = 'spin 1s linear infinite';
            spinner.style.margin = '0 auto 20px auto';
            notificationContainer.appendChild(spinner);

            // Add the notification container to the overlay
            overlay.appendChild(notificationContainer);

            // Add a cancel button
            const cancelButton = this.createCancelButton(overlay);
            overlay.appendChild(cancelButton);

            // Add the overlay to the body
            document.body.appendChild(overlay);

            // Create an invisible iframe to handle the form submission
            const hiddenIframe = document.createElement('iframe');
            hiddenIframe.name = '3DSecureHiddenFrame';
            hiddenIframe.style.width = '0';
            hiddenIframe.style.height = '0';
            hiddenIframe.style.border = 'none';
            hiddenIframe.style.display = 'none';
            document.body.appendChild(hiddenIframe);

            // Create a hidden form for the ACS submission
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = acsUrl;
            form.target = '3DSecurePopup'; // Submit to a popup window
            form.style.display = 'none';

            // Add the payload as a hidden field
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'PaReq'; // This is the standard name for 3D Secure
            input.value = acsPayload;
            form.appendChild(input);

            // Add TermUrl (where the bank should return control after authentication)
            const termUrlInput = document.createElement('input');
            termUrlInput.type = 'hidden';
            termUrlInput.name = 'TermUrl';
            termUrlInput.value = window.location.origin + '/payment/callback';
            form.appendChild(termUrlInput);

            // Add MD (merchant data)
            const mdInput = document.createElement('input');
            mdInput.type = 'hidden';
            mdInput.name = 'MD';
            mdInput.value = this.trace || ''; // Use the trace as the merchant data
            form.appendChild(mdInput);

            // Add the form to the document
            document.body.appendChild(form);

            // Create a reopen button for the notification (initially hidden)
            const reopenButton = document.createElement('button');
            reopenButton.textContent = 'Reopen Authentication Window';
            reopenButton.style.padding = '12px 24px';
            reopenButton.style.backgroundColor = 'rgba(79, 70, 229, 0.9)';
            reopenButton.style.color = 'white';
            reopenButton.style.border = 'none';
            reopenButton.style.borderRadius = '8px';
            reopenButton.style.fontWeight = 'bold';
            reopenButton.style.cursor = 'pointer';
            reopenButton.style.boxShadow = '0 4px 12px rgba(79, 70, 229, 0.4)';
            reopenButton.style.transition = 'all 0.2s ease';
            reopenButton.style.display = 'none'; // Hidden initially

            // Add hover and active effects
            reopenButton.onmouseover = () => {
                reopenButton.style.backgroundColor = 'rgba(79, 70, 229, 1)';
                reopenButton.style.transform = 'translateY(-2px)';
                reopenButton.style.boxShadow = '0 6px 16px rgba(79, 70, 229, 0.5)';
            };
            reopenButton.onmouseout = () => {
                reopenButton.style.backgroundColor = 'rgba(79, 70, 229, 0.9)';
                reopenButton.style.transform = 'translateY(0)';
                reopenButton.style.boxShadow = '0 4px 12px rgba(79, 70, 229, 0.4)';
            };
            reopenButton.onmousedown = () => {
                reopenButton.style.transform = 'translateY(1px)';
                reopenButton.style.boxShadow = '0 2px 8px rgba(79, 70, 229, 0.4)';
            };

            // Function to open the popup and submit the form
            let popupWindow = null;
            const openPopupAndSubmitForm = () => {
                // Close existing popup if open
                if (popupWindow && !popupWindow.closed) {
                    popupWindow.close();
                }

                // Open new popup window
                const width = 450;
                const height = 600;
                const left = (window.innerWidth - width) / 2 + window.screenX;
                const top = (window.innerHeight - height) / 2 + window.screenY;
                popupWindow = window.open(
                    'about:blank',
                    '3DSecurePopup',
                    `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,status=yes`
                );

                // Submit the form to the popup
                if (popupWindow) {
                    popupWindow.focus();
                    form.submit();

                    // Update the UI to show the authentication is in progress
                    spinner.style.display = 'none';
                    loadingText.innerHTML = 'Authentication window is open.<br>Please complete the verification in the popup window.';

                    // Show the reopen button
                    reopenButton.style.display = 'inline-block';
                    notificationContainer.appendChild(reopenButton);

                    // Check if popup was blocked
                    setTimeout(() => {
                        if (!popupWindow || popupWindow.closed || popupWindow.closed === undefined) {
                            loadingText.innerHTML = '<span style="color:#f87171">Popup was blocked!</span><br>Please click the button below to open the authentication window.';
                        }
                    }, 1000);
                }
            };

            // Attach the open popup handler to the reopen button
            reopenButton.onclick = openPopupAndSubmitForm;

            // Open the popup after a brief delay
            setTimeout(openPopupAndSubmitForm, 1000);

            this.isLoading = false;
            console.log('Opening 3D Secure popup for ACS URL:', acsUrl);
        },

                /**
         * Integrates Zimswitch payment directly in Vue.js component
         * @param {Object} paymentData - The payment data from Laravel
         */
        integrateZimswitchPayment(paymentData) {
            this.trace = paymentData.trace;

            // Show the integrated payment form
            this.showZimswitchIntegratedPayment(
                paymentData.authConfig.checkoutUrl,
                paymentData.checkoutId,
                paymentData.amount,
                paymentData.currency
            );
        },

                /**
         * Shows the integrated Zimswitch payment form using EFTPay widget
         * Matches the behavior and styling of test-zimswitch.html
         * @param {string} checkoutUrl - The EFTPay checkout URL
         * @param {string} checkoutId - The checkout ID
         * @param {string} amount - The payment amount
         * @param {string} currency - The payment currency
         */
        showZimswitchIntegratedPayment(checkoutUrl, checkoutId, amount, currency) {
            // Create overlay with same styling as test page
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create container with same styling as test page
            const container = document.createElement('div');
            container.style.cssText = `
                background: white;
                padding: 30px;
                border-radius: 10px;
                max-width: 500px;
                width: 90%;
                position: relative;
                box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                animation: slideIn 0.3s ease;
            `;

            // Create close button (same as test page)
            const closeBtn = document.createElement('button');
            closeBtn.textContent = 'Close';
            closeBtn.style.cssText = `
                position: absolute;
                top: 10px;
                right: 10px;
                background: #dc3545;
                color: white;
                border: none;
                padding: 5px 10px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                z-index: 1;
            `;
            closeBtn.onclick = () => {
                document.body.removeChild(overlay);
                this.isLoading = false;
            };

            // Create title (same as test page)
            const title = document.createElement('h3');
            title.textContent = `Zimswitch Payment - ${currency} ${this.formatAmount(amount)}`;
            title.style.cssText = `
                margin: 0 0 20px 0;
                color: #333;
                text-align: center;
                font-size: 18px;
            `;

            // Create form for EFTPay widget (exactly like test page)
            const form = document.createElement('form');
            form.className = 'paymentWidgets';
            form.setAttribute('data-brands', 'PRIVATE_LABEL');
            form.action = '#';
            form.method = 'POST';

            // Add hidden input for resourcePath (will be populated by EFTPay)
            const resourcePathInput = document.createElement('input');
            resourcePathInput.type = 'hidden';
            resourcePathInput.name = 'resourcePath';
            form.appendChild(resourcePathInput);

            // Handle form submission exactly like test page
            form.onsubmit = (e) => {
                e.preventDefault();
                console.log('Form submitted');
                console.log('Form data:', new FormData(form));

                // Get resourcePath from form data
                const formData = new FormData(form);
                const resourcePath = formData.get('resourcePath') || resourcePathInput.value;

                console.log('Resource path:', resourcePath);

                if (resourcePath) {
                    // Close the overlay first
                    document.body.removeChild(overlay);
                    // Then handle payment completion
                    this.handleZimswitchPaymentCompletion(resourcePath, null);
                } else {
                    console.error('No resource path found in form submission');
                    this.$swal.fire({
                        title: 'Error',
                        text: 'Payment data is missing. Please try again.',
                        icon: 'error'
                    });
                }
                return false;
            };

            // Assemble the container
            container.appendChild(closeBtn);
            container.appendChild(title);
            container.appendChild(form);
            overlay.appendChild(container);

            // Add the overlay to the body
            document.body.appendChild(overlay);

            // Set required cookie for EFTPay (exactly like working pay.php)
            document.cookie = "cookie_eftcorp=https://eftpaygateway.com/; SameSite=Strict; path=/";

            // Configure EFTPay widget options (exactly like working pay.php)
            window.wpwlOptions = {
                style: "plain",
                brandDetection: false,
                showPlaceholders: false,
                onReady: function () {
                    console.log('EFTPay widget onReady called');

                    // Function to apply ZimSwitch branding
                    const applyZimSwitchBranding = () => {
                        if (window.jQuery) {
                            console.log('Applying ZimSwitch branding with jQuery');
                            window.jQuery('.wpwl-group-brand').before("<img src='http://www.zimswitchonline.co.zw/wp-content/uploads/2022/06/favicon.1ee90efd.svg' width='200' style='vertical-align:middle;margin:50px 50px'></img>");
                            window.jQuery('.wpwl-control-brand option[value="PRIVATE_LABEL"]').text("ZimSwitch");
                            window.jQuery('.wpwl-label-cardNumber').text("ZimSwitch Card");
                        } else {
                            console.log('jQuery not available, using vanilla JS');
                            // Fallback to vanilla JS
                            const brandGroup = document.querySelector('.wpwl-group-brand');
                            if (brandGroup) {
                                const img = document.createElement('img');
                                img.src = 'http://www.zimswitchonline.co.zw/wp-content/uploads/2022/06/favicon.1ee90efd.svg';
                                img.width = 200;
                                img.style.cssText = 'vertical-align:middle;margin:50px 50px';
                                brandGroup.parentNode.insertBefore(img, brandGroup);
                            }

                            const brandSelect = document.querySelector('.wpwl-control-brand option[value="PRIVATE_LABEL"]');
                            if (brandSelect) {
                                brandSelect.textContent = "ZimSwitch";
                            }

                            const cardNumberLabel = document.querySelector('.wpwl-label-cardNumber');
                            if (cardNumberLabel) {
                                cardNumberLabel.textContent = "ZimSwitch Card";
                            }
                        }
                    };

                    // Load jQuery if not available
                    if (!window.jQuery) {
                        console.log('Loading jQuery...');
                        const jqueryScript = document.createElement('script');
                        jqueryScript.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
                        jqueryScript.onload = function() {
                            console.log('jQuery loaded successfully');
                            applyZimSwitchBranding();
                        };
                        jqueryScript.onerror = function() {
                            console.log('jQuery failed to load, using vanilla JS');
                            applyZimSwitchBranding();
                        };
                        document.head.appendChild(jqueryScript);
                    } else {
                        console.log('jQuery already available');
                        applyZimSwitchBranding();
                    }
                }
            };

            // Add CSS animations and widget styling
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes slideIn {
                    from { transform: translateY(-20px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }

                /* EFTPay widget styling */
                .paymentWidgets {
                    min-height: 300px;
                    padding: 20px;
                }

                .wpwl-form {
                    background: transparent !important;
                }

                .wpwl-group {
                    margin-bottom: 15px;
                }

                .wpwl-control {
                    width: 100% !important;
                    padding: 8px !important;
                    border: 1px solid #ddd !important;
                    border-radius: 4px !important;
                    font-size: 14px !important;
                }

                .wpwl-button {
                    background: #007bff !important;
                    color: white !important;
                    padding: 10px 20px !important;
                    border: none !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    font-size: 16px !important;
                    width: 100% !important;
                }

                .wpwl-button:hover {
                    background: #0056b3 !important;
                }

                .wpwl-label {
                    font-weight: bold !important;
                    margin-bottom: 5px !important;
                    display: block !important;
                }
            `;
            document.head.appendChild(style);

                        // Add the EFTPay script (exactly like working pay.php)
            const script = document.createElement('script');
            script.src = `https://${checkoutUrl}${checkoutId}`;
            script.setAttribute('crossorigin', 'anonymous');
            script.async = true;

            console.log('Loading EFTPay script:', script.src);
            console.log('Checkout URL:', checkoutUrl);
            console.log('Checkout ID:', checkoutId);

            script.onload = () => {
                console.log('EFTPay widget script loaded successfully');
                this.isLoading = false;
            };
            script.onerror = (error) => {
                console.error('Failed to load EFTPay widget script:', error);
                console.error('Script URL that failed:', script.src);
                this.isLoading = false;
                this.$swal.fire({
                    title: 'Error',
                    text: 'Failed to load payment widget. Please try again.',
                    icon: 'error'
                });
                // Remove overlay on error
                if (document.body.contains(overlay)) {
                    document.body.removeChild(overlay);
                }
            };
            document.head.appendChild(script);

            console.log('Displaying integrated EFTPay payment form with checkout ID:', checkoutId);
        },

        /**
         * Handles Zimswitch payment completion
         * @param {string} resourcePath - The resource path from EFTPay
         * @param {HTMLElement} overlay - The payment overlay to remove
         */
        async handleZimswitchPaymentCompletion(resourcePath, overlay) {
            try {
                // Show loading state
                this.isLoading = true;

                // Call Laravel backend to check payment status using resource path
                const response = await axios.post('/api/v1/zimswitch/payment-status', {
                    resourcePath: resourcePath,
                    trace: this.trace
                });

                // Remove the overlay
                if (overlay && overlay.parentNode) {
                    document.body.removeChild(overlay);
                }

                if (response.data.success) {
                    // Payment successful
                    this.$swal.fire({
                        title: 'Payment Successful!',
                        text: 'Your payment has been processed successfully.',
                        icon: 'success',
                        confirmButtonText: 'Continue'
                    }).then(() => {
                        window.location.href = this.returnUrl;
                    });
                } else {
                    // Payment failed
                    this.$swal.fire({
                        title: 'Payment Failed',
                        text: response.data.message || 'Your payment could not be processed.',
                        icon: 'error',
                        confirmButtonText: 'Try Again'
                    });
                }
            } catch (error) {
                console.error('Error processing payment completion:', error);

                // Remove the overlay
                if (overlay && overlay.parentNode) {
                    document.body.removeChild(overlay);
                }

                this.$swal.fire({
                    title: 'Error',
                    text: 'An error occurred while processing your payment.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Creates a payment form for Zimswitch using EFTPay (deprecated - keeping for compatibility)
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
