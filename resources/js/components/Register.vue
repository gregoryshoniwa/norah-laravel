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
                      <div class="col-lg-6">
                        <div class="form-group">
                          <input
                            v-model="firstName"
                            id="fname"
                            name="fname"
                            type="text"
                            placeholder="First Name"
                          />
                        </div>
                      </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                          <input
                            v-model="lastName"
                            id="lname"
                            name="lname"
                            type="text"
                            placeholder="Last Name"
                          />
                        </div>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group">
                          <input
                            v-model="email"
                            id="email"
                            name="email"
                            type="text"
                            placeholder="Email"
                          />
                        </div>
                      </div>
                      <div class="col-lg-6">
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
                      <div class="col-lg-6">
                        <div class="form-group">
                          <input
                            v-model="confirmPassword"
                            id="pwd_2"
                            name="pwd_2"
                            placeholder="Confirm Password"
                            type="password"
                          />
                        </div>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group">
                          <input
                            v-model="companyName"
                            id="company_name"
                            name="company_name"
                            placeholder="Company Name"
                            type="text"
                          />
                        </div>
                      </div>
                      <div class="col-sm-12 col-12 mb-20">
                        <div class="checkbox style3">
                          <input v-model="terms" type="checkbox" id="test_1" />
                          <label for="test_1">
                            I Agree with the
                            <router-link class="link style1" to="/privacy-policy">Privacy Policies</router-link>
                          </label>
                        </div>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group">
                          <button class="btn style1" @click="register">Register Now</button>
                        </div>
                      </div>
                      <div class="col-md-12">
                        <p class="mb-0">
                          Have an Account?
                          <router-link class="link style1" to="/login">Sign In</router-link>
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
    name: "Register",
    components: {
      Loader
    },
    data: () => ({
      firstName: "",
      lastName: "",
      email: "",
      password: "",
      confirmPassword: "",
      companyName: "",
      terms: null,
      isLoading: false
    }),
    methods: {
      async register() {
        var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
        if (this.firstName == "") {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid First Name!",
            "error"
          );
        } else if (this.lastName == "") {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid Last Name!",
            "error"
          );
        } else if (!mailformat.test(this.email)) {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid email address!",
            "error"
          );
        } else if (this.password == "") {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid password!",
            "error"
          );
        } else if (this.password.length < 10) {
          this.$swal.fire(
            "Login error!",
            "Please enter a Stronger password!",
            "error"
          );
        } else if (this.confirmPassword == "") {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid confirm password!",
            "error"
          );
        } else if (this.confirmPassword != this.password) {
          this.$swal.fire(
            "Login error!",
            "Your password does not match the confirm password!",
            "error"
          );
        } else if (this.companyName == "") {
          this.$swal.fire(
            "Login error!",
            "Please enter a valid company name!",
            "error"
          );
        } else if (this.terms == null) {
          this.$swal.fire(
            "Login error!",
            "Please confirm you agree with the terms & conditions!",
            "error"
          );
        } else {
          this.isLoading = true;

          try {
            const response = await axios.post("/api/v1/auth/admin-sign-up", {
              firstName: this.firstName,
              lastName: this.lastName,
              email: this.email,
              password: this.password,
              companyName: this.companyName
            });

            this.isLoading = false;

            if (response.data.statusCode == 201) {
              this.$swal.fire(
                "Good job!",
                "You have successfully registered your profile! Please check your email for confirmation.",
                "success"
              );

              this.$router.push('/login');
            } else {
              console.error("Unexpected response:", response.data);
              this.$swal.fire(
                "Registration error!",
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
              this.$swal.fire(
                "Registration error!",
                error.response.data.message,
                "error"
              );
            } else {
              console.error("Registration error:", error);
              this.$swal.fire(
                "Registration error!",
                "An unexpected error occurred",
                "error"
              );
            }
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
