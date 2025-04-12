<template>
    <div class="Login-wrap ptb-100">
      <loader v-if="isLoading" />
      <img src="assets/images/section-shape-2.png" alt="Image" class="section-shape-two" />

      <div class="container">
        <div class="row">
          <div class="col-xl-6 offset-xl-3 col-lg-8 offset-lg-2 col-md-10 offset-md-1">
            <div class="login-form-wrap">
              <div class="login-header">
                <div style="display: flex; align-items: center; justify-content: center;">
                  <img src="assets/images/logo.fw.png" alt="logo" />
                  <span style="margin-top: 10px;">
                    <h3>NORAH : Payment-Gateway</h3>
                  </span>
                </div>
              </div>
              <div class="login-form">
                <div class="login-body">
                  <form class="form-wrap" @submit.prevent>
                    <div class="row">
                      <div class="col-lg-12">
                        <div class="form-group">
                          <input
                            v-model="email"
                            id="text"
                            name="email"
                            type="text"
                            placeholder="Username Or Email Address"
                          />
                        </div>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group">
                          <input
                            v-model="password"
                            id="pwd"
                            name="pwd"
                            type="password"
                            placeholder="Password"
                          />
                        </div>
                      </div>
                      <div class="col-lg-6 col-md-6 col-sm-6 col-6">
                        <div class="checkbox style3">
                          <input type="checkbox" id="test_1" />
                          <label for="test_1">Remember Me</label>
                        </div>
                      </div>
                      <div class="col-lg-6 col-md-6 col-sm-6 col-6 text-end mb-20">
                        <router-link to="/recover-password" class="link style1">Forgot Password?</router-link>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group">
                          <button class="btn style1" @click="login">Login</button>
                        </div>
                      </div>
                      <div class="col-md-12 text-center">
                        <p class="mb-0">
                          Don’t Have an Account?
                          <router-link class="link style1" to="/register">Create One</router-link>
                        </p>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>

  <script>
  import Loader from "./Loader.vue";
  import axios from 'axios';

  export default {
    name: "Login",
    components: {
      Loader
    },
    data() {
      return {
        email: "",
        password: "",
        isLoading: false,
        db: null // Add db property to hold the IndexedDB instance
      };
    },
    async created() {
      // Initialize db asynchronously
      this.db = await this.$db;
    },
    methods: {
      async login() {
        if (this.email === "") {
          this.$swal.fire("Login error!", "Please enter a valid Email!", "error");
          return;
        }
        if (this.password === "") {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid Password!",
            "error"
          );
          return;
        }

        this.isLoading = true;

        try {
          const response = await axios.post("/api/v1/auth/sign-in", {
            email: this.email,
            password: this.password
          });

          this.isLoading = false;

          if (response.data.token) {
            const {
              token,
              refreshToken,
              tokenExpiryDate,
              refreshTokenExpiryDate,
              user_id,
              role,
              fullName,
              email
            } = response.data;

            this.$swal.fire(
              "Success!",
              "You have successfully logged in!",
              "success"
            );

            // Save session data to IndexedDB
            // const tx = this.db.transaction("sessions", "readwrite");
            // const store = tx.objectStore("sessions");
            // await store.put({ key: "session_data", ...response.data });
            // await tx.done;

            this.$router.push('/dashboard');
          } else {
            console.error("Unexpected response:", response.data);
            this.$swal.fire(
              "Login error!",
              "Unexpected response from server",
              "error"
            );
          }
        } catch (error) {
          this.isLoading = false;

          if (
            error.response &&
            error.response.data &&
            error.response.data.message
          ) {
            this.$swal.fire("Login error!", error.response.data.message, "error");
          } else {
            console.error("Login error:", error);
            this.$swal.fire(
              "Login error!",
              "An unexpected error occurred",
              "error"
            );
          }
        }
      }
    }
  };
  </script>
  <style>
  body {
    background: #010647 !important;
  }
  </style>
