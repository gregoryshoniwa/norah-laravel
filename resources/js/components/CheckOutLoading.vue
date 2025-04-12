<template>
  <div class="container">

    <form action @submit.prevent="confirmPost" style="height: 450px;">
      <div class="login-header">
        <div style="display: flex; align-items: center; justify-content: center;margin: 15px;">
          <img src="assets/images/logo.fw.png" alt="logo" />
          <span>
            <h3>NORAH : Processing</h3>
          </span>
        </div>
      </div>
      <div class="row">

          <div v-show="logo === 'INNBUCKS'" style="display: flex;align-items: center; justify-content: center;">
            <div class="timer-text">{{ formattedTimeLeft }} time left</div>
          </div>

        <div style="display: flex;flex-direction: column;align-items: center;">
          <img v-if="logo === 'ECOCASH'" src="assets/images/payments/ecocash-logo.png" alt="ecocash logo" style="width: 200px; height: auto;" />
          <img v-else-if="logo === 'INNBUCKS'" src="assets/images/payments/innbucks.png" alt="innbucks logo" style="width: 200px; height: auto;"/>

          <div v-if="logo === 'INNBUCKS'" class="info-message-number">
            {{qrcode}}
          </div>
          <vue-qrcode v-if="logo === 'INNBUCKS'" :value="qrcode" :size=size />

          <div v-show="logo === 'ECOCASH'">
            <div class="countdown-timer" :class="{ 'warning': timeLeft <= 10 }">
            <svg>
              <circle cx="50" cy="50" r="45"></circle>
              <circle cx="50" cy="50" r="45" class="progress"></circle>
            </svg>
            <div class="timer-text">{{ timeLeft }} sec</div>
          </div>



        </div>

        </div>

        <div v-if="logo === 'ECOCASH'" class="info-message">
           Please complete the transaction on your phone.
        </div>
        <div v-else-if="logo === 'INNBUCKS'" class="info-message">
           Please scan the above QR Code to complete the transaction.
        </div>



      </div>
    </form>
  </div>
</template>
<script>
import VueQrcode from 'vue-qrcode';
export default {
  name: "CheckOutLoading",
  components: {
    VueQrcode
  },
  data: function() {

    return {
      timeLeft: 0, // Countdown time in seconds
      intervalId: null,
      phoneNumber: null,
      logo: null,
      size:150,
      qrcode: null,
      reference: null,
    };
  },
  computed: {
    formattedTimeLeft() {
      const minutes = Math.floor(this.timeLeft / 60);
      const seconds = this.timeLeft % 60;
      return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    },
  },
  mounted (){
    this.startCountdown();
    this.getPhoneNumberFromQueryParams();
     this.fetchTransactionConfirmation();
  },
  beforeDestroy() {
    clearInterval(this.intervalId);
  },
  watch: {

  },

  methods: {

    async fetchTransactionConfirmation() {
     // console.log("hi from greg test");
      try {
        const response = await this.axios.get(
          `/api/v1/transaction/confirmation/${this.logo}/` + this.reference
        );
        if(response.data.status == 'SUCCESS'){
          // set 5sec delay
          this.$swal.fire(
              "Transaction successful",
              "Your transaction was successfully completed, we are now redirecting you to the merchant page.",
              "success"
            );
          setTimeout(() => {
            window.location.href = response.data.returnUrl;
          },5000)

        }else{
          this.$swal.fire(
              "Transaction error",
              response.data.responseMessage,
              "error"
            );
          setTimeout(() => {
            window.location.href = response.data.returnUrl;
          },5000)
        }
        console.log(response.data);
        // Handle the response data as needed
      } catch (error) {
        this.$swal.fire(
              "Transaction error",
              error,
              "success"
            );
        console.error('There was an error!', error);
      }
    },
    startCountdown() {
      const circumference = 2 * Math.PI * 45;
      const progressCircle = this.$el.querySelector('.progress');
      progressCircle.style.strokeDasharray = circumference;
      progressCircle.style.strokeDashoffset = circumference;

      this.timeLeft = 600; // 10 minutes in seconds

      this.intervalId = setInterval(() => {
        if (this.timeLeft > 0) {
          this.timeLeft--;
          const offset = circumference - (this.timeLeft / 600) * circumference; // Update to 600 seconds
          progressCircle.style.strokeDashoffset = offset;
        } else {
          clearInterval(this.intervalId);
        }
      }, 1000);
    },
    getPhoneNumberFromQueryParams() {
      const phone = this.$route.query.phone;
      const logo = this.$route.query.method;
      const qr = this.$route.query.qrcode;
      const reference = this.$route.query.reference;
      this.phoneNumber = phone || 'unknown';
      this.logo = logo;
      this.qrcode = qr;
      this.reference = reference;

      if(this.logo === 'INNBUCKS'){
        this.timeLeft = 600;
      }
      if(this.logo === 'ECOCASH'){
          this.timeLeft = 60;
      }

    }
  }
};
</script>
<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600&display=swap");

* {
  font-family: "Poppins", sans-serif;
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  outline: none;
  border: none;
  text-transform: capitalize;
  transition: all 0.2s linear;
}

.countdown-timer.warning .progress {
  stroke: red;
}

.info-message {
  text-align: center;
  font-size: 18px;
  margin-top: 10px;
  /* margin-bottom: 20px; */
  color: #333;
}

.info-message-number {
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  margin-top: 10px;
  /* margin-bottom: 20px; */
  color: #333;
}


.countdown-timer {

  width: 100px;
  height: 100px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.countdown-timer svg {
  position: absolute;
  width: 100px;
  height: 100px;
  transform: rotate(-90deg);
}

.countdown-timer circle {
  fill: none;
  stroke-width: 10;
}

.countdown-timer circle:first-child {
  stroke: #e6e6e6;
}

.countdown-timer .progress {
  stroke: #00A9A4;
  stroke-linecap: round;
  transition: stroke-dashoffset 1s linear;
}

.timer-text {
  position: absolute;
  font-size: 20px;
  font-weight: bold;
}

.loader {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 100px; /* Adjust the width as needed */
  height: 200px; /* Adjust the height as needed */
  display: flex;
  justify-content: center;
  align-items: center;
}

.loader div {
  width: 12px;
  height: 12px;
  margin: 0 10px 0 0;
  border-radius: 50px;
  transform-origin: 50% 0;
  display: inline-block;
  animation: bouncing 1.4s linear infinite;
}

.loading {
  opacity: 0.75;
  cursor: not-allowed;
}

/* Optional: simple CSS animation */
@keyframes spinner {
  to {
    transform: rotate(360deg);
  }
}

.loading::after {
  content: "";
  width: 16px;
  height: 16px;
  border: 2px solid #fff;
  border-top-color: #333;
  border-radius: 50%;
  display: inline-block;
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  animation: spinner 0.6s linear infinite;
}

.container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 25px;
  min-width: 100%;
  min-height: 100vh;
  background: linear-gradient(90deg, #010647 60%, #00A9A4 40%);
}

.container form {
  padding: 20px;
  width: 800px;
  background: #fff;
  box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
}

.container form .row {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
}

.container form .row .col {
  flex: 1 1 250px;
}

.container form .row .col .title {
  font-size: 20px;
  color: #333;
  padding-bottom: 5px;
  text-transform: uppercase;
}

.container form .row .col .inputBox {
  margin: 15px 0;
}

.container form .row .col .inputBox span {
  margin-bottom: 10px;
  display: block;
}

.container form .row .col .inputBox input {
  width: 100%;
  border: 1px solid #ccc;
  padding: 10px 15px;
  font-size: 15px;
  text-transform: none;
}

.container form .row .col .inputBox input:focus {
  border: 1px solid #000;
}

.container form .row .col .flex {
  display: flex;
  gap: 15px;
}

.container form .row .col .flex .inputBox {
  margin-top: 5px;
}

.container form .row .col .inputBox img {
  height: 34px;
  margin-top: 5px;
  filter: drop-shadow(0 0 1px #000);
}

.container form .submit-btn {
  width: 100%;
  padding: 12px;
  font-size: 17px;
  background: #010647;
  color: #fff;
  margin-top: 5px;
  cursor: pointer;
}
.container form .charges-btn {
  width: 100%;
  padding: 12px;
  font-size: 17px;
  background: #727272;
  color: #fff;
  margin-top: 5px;
  text-align: center;
}

.container form .submit-btn:hover {
  background: #303795;
}

img:hover {
  cursor: pointer;
}


</style>
