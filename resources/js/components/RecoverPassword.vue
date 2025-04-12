<template>
  <div class="Login-wrap ptb-100">
    <loader v-if="isLoading" />
    <div class="container">
      <div class="row">
        <div class="col-xl-6 offset-xl-3 col-lg-8 offset-lg-2 col-md-10 offset-md-1">
          <div class="login-form-wrap">
            <div class="login-header">
              <h3>Recover Password</h3>
              <p>We will send a verification code to your mail to reset your password.</p>
            </div>
            <div class="login-form">
              <div class="login-body">
                <form class="form-wrap" @submit.prevent>
                  <div class="row">
                    <div class="col-lg-12">
                      <div class="form-group">
                        <input
                          id="email"
                          name="email"
                          type="email"
                          v-model="email"
                          placeholder="Username/Email"
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
  name: "RecoverPassword",
  components: {
      Loader
    },
  data: () => ({
    email: "",
    isLoading: false
  }),
  methods: {
    async submit() {
      var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
      if (!mailformat.test(this.email)) {
        this.$swal.fire(
          "Recover Password error!",
          "Please enter a valid email address!",
          "error"
        );
      } else {
        this.isLoading = true;

        try {
          const response = await axios.post(
            "/api/v1/auth/forgot-password",
            {
              email: this.email
            }
          );

          this.isLoading = false;

          if (response.data.statusCode == 200) {
            this.$swal.fire(
              "Good job!",
              "You have successfully submited your password recovery request! Please check you email for confirmation.",
              "success"
            );
            this.$router.push('/');
          } else {
            console.error("Unexpected response:", response.data);
            this.$swal.fire(
              "Recover Password error!",
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
              "Recover Password error!",
              error.response.data.message,
              "error"
            );
          } else {
            console.error("Recover Password error:", error);
            this.$swal.fire(
              "Recover Password error!",
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
