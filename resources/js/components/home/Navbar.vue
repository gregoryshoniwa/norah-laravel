<template>
  <div :class="['header-wrap', { sticky: isSticky }]">
    <!-- Top Header -->
    <div class="header-top" :class="{ open: open }">
      <button type="button" class="close-sidebar" @click="open = !open">
        <i class="ri-close-fill"></i>
      </button>
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-8 col-md-9">
            <div class="header-top-left">
              <ul class="contact-info list-style">
                <li>
                  <i class="flaticon-call"></i>
                  <a href="tel:02459271449">(+263) 787 193 326</a>
                </li>
                <li>
                  <i class="flaticon-call"></i>
                  <a href="tel:02459271449">(+263) 8677 190 288</a>
                </li>
                <li>
                  <i class="flaticon-email-1"></i>
                  <a href="mailto:hello@ruxa.com">info@prodleader.co.zw</a>
                </li>

                <li>
                  <i class="flaticon-pin"></i>
                  <p>194 Baines Avenue, Harare, Zimbabwe</p>
                </li>
              </ul>
            </div>
          </div>

          <!-- <div class="col-lg-4 col-md-3">
            <div class="header-top-right">
              <ul class="header-top-menu list-style">
                <li><router-link to="/contact">Support</router-link></li>
                <li><router-link to="/contact">Help</router-link></li>
              </ul>
            </div>
          </div> -->
        </div>
      </div>
    </div>
    <!-- End Top Header -->

    <!-- Navbar -->
    <div class="header-bottom">
      <div class="container">
        <nav class="navbar navbar-expand-md navbar-light">
          <router-link class="navbar-brand" to="/">
            <img
              class="logo-light"
              src="assets/images/logo.png"
              alt="logo"
            />
            <img
              class="logo-dark"
              src="assets/images/logo-white.png"
              alt="logo"
            />
          </router-link>

          <div class=" navbar-collapse" >
            <ul class="navbar-nav ms-auto">

              <li class="nav-item" @click="scrollToSection('Home')">
                <router-link to="/" class="nav-link">Home</router-link>
              </li>

              <li class="nav-item" @click="scrollToSection('About')">
                <router-link to="/" class="nav-link">About</router-link>
              </li>

              <li class="nav-item" @click="scrollToSection('Platform')">
                <router-link to="/" class="nav-link">Platform</router-link>
              </li>

              <li class="nav-item" @click="scrollToSection('Currency')">
                <router-link to="/" class="nav-link">Currencies</router-link>
              </li>

              <li class="nav-item" @click="scrollToSection('Services')">
                <router-link to="/" class="nav-link">Services</router-link>
              </li>


            </ul>

            <div class="others-options d-flex align-items-cente">

              <div class="header-btn" style="margin-left: 150px;">
                <router-link to="/register" class="btn style1"
                  >Register Now</router-link
                >
              </div>
            </div>
          </div>

          <div class="mobile-bar-wrap">
            <div
              class="mobile-sidebar"
              @click="open = !open"
              :aria-pressed="open ? 'true' : 'false'"
              v-bind:class="{ open: button_open_state }"
              v-on:click="button_open_state = !button_open_state"
            >
              <i class="ri-menu-4-line"></i>
            </div>

            <div
            style="margin-left: 20px;"
              class="navbar-toggler mobile-menu xl-none"
              @click="active = !active"
              :aria-pressed="active ? 'true' : 'false'"
              v-bind:class="{ active: button_active_state }"
              v-on:click="button_active_state = !button_active_state"
            >
              <a href="javascript:void(0);">
                <i class="ri-menu-line"></i>
                <i class="ri-close-line"></i>
              </a>
            </div>
          </div>
        </nav>
      </div>
    </div>
    <!-- End Navbar -->
  </div>
</template>

<script>
export default {
  name: "Navbar",
  data: () => ({
    isSticky: false,
    active: false,
    button_active_state: false,
    search: false,
    button_search_state: false,
    open: false,
    button_open_state: false,
  }),
  mounted() {
    const that = this;
    window.addEventListener("scroll", () => {
      let scrollPos = window.scrollY;
      if (scrollPos >= 100) {
        that.isSticky = true;
      } else {
        that.isSticky = false;
      }
    });
  },
  methods: {
    scrollToSection(sectionName) {

      const targetSection = document.getElementById(sectionName);
      if (targetSection) {

        window.scrollTo({
          top: targetSection.offsetTop,
          behavior: 'smooth',
        });
      }
    },
  },
};
</script>
