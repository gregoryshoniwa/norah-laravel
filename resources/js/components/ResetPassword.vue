<!-- <template>
    <div>
        <h1>Reset Password</h1>
        <form @submit.prevent="resetPassword">
            <input type="hidden" v-model="token" />
            <input type="hidden" v-model="email" />
            <div>
                <label for="password">New Password:</label>
                <input type="password" v-model="password" required />
            </div>
            <div>
                <label for="password_confirmation">Confirm Password:</label>
                <input type="password" v-model="password_confirmation" required />
            </div>
            <button type="submit">Reset Password</button>
        </form>
    </div>
</template> -->

<template>
    <div class="Login-wrap ptb-100">
        <loader v-if="isLoading" />
      <div class="container">
        <div class="row">
          <div class="col-xl-6 offset-xl-3 col-lg-8 offset-lg-2 col-md-10 offset-md-1">
            <div class="login-form-wrap">
              <div class="login-header">
                <h3>New Password Reset</h3>
                <p>Please enter the new password you want to set.</p>
              </div>
              <div class="login-form">
                <div class="login-body">
                  <form class="form-wrap" @submit.prevent>
                    <div class="row">
                      <div class="col-lg-12">
                        <div class="form-group">
                          <input
                            id="new_password"
                            name="new_password"
                            type="password"
                            v-model="password"
                            placeholder="New Password"
                            required
                          />

                        </div>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group">

                          <input
                            id="confirm"
                            name="confirm_password"
                            type="password"
                            v-model="password_confirmation"
                            placeholder="Confirm Password"
                            required
                          />
                        </div>
                      </div>
                      <!-- <div class="col-lg-12">
                                              <div class="form-group">
                                                  <input id="pwd" name="pwd" type="password" placeholder="Old Password">
                                              </div>
                      </div>-->
                      <div class="col-lg-6 col-md-6 col-sm-6 col-6">
                        <router-link to="/login" class="link style1">Back to Login</router-link>
                      </div>
                      <div class="col-lg-6 col-md-6 col-sm-6 col-6 text-end mb-20">
                        <router-link to="/register" class="link style1">Back to Registration</router-link>
                      </div>
                      <div class="col-lg-12">
                        <div class="form-group mb-0">
                          <button class="btn style1 w-100 d-block" @click="submit">Submit</button>
                        </div>
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
import axios from 'axios';
import Loader from "./Loader.vue";
export default {
    components: {
      Loader
    },
    data() {
        return {
            token: this.$route.query.token, // Get the token from the query string
            email: this.$route.query.email, // Get the email from the query string
            password: '',
            password_confirmation: '',
        };
    },
    methods: {
        async submit() {
            try {
                const response = await axios.post('/api/v1/reset-password', {
                    token: this.token,
                    email: this.email,
                    password: this.password,
                    password_confirmation: this.password_confirmation,
                });

                // alert(response.data.message);
                    this.$swal.fire(
                    "Good job!",
                    response.data.message,
                    "success"
                );
                this.$router.push('/login'); // Redirect to login page
            } catch (error) {
                if (error.response) {
                    this.$swal.fire(
                        "Recover Password error!",
                        error.response.data.message,
                        "error"
                    );
                    // Server responded with a status other than 2xx
                    // alert(error.response.data.message || 'An error occurred.');
                } else {
                    console.error("Recover Password error:", error);
                        this.$swal.fire(
                        "Recover Password error!",
                        "An unexpected error occurred",
                        "error"
                    );
                    // Network or other error
                    // console.error('Error resetting password:', error);
                    // alert('An error occurred. Please try again.');
                }
            }
        },
    },
};
</script>
