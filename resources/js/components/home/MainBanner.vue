<template>
  <div id="Home" class="hero-wrap style2 bg-stratos">
    <img
      src="assets/images/hero/hero-shape-9.png"
      alt="Image"
      class="hero-shape-three"
    />
    <img
      src="assets/images/hero/hero-shape-19.png"
      alt="Image"
      class="hero-shape-six"
    />
    <div class="container">
      <div class="row gx-5 align-items-center">
        <div class="col-md-6">
          <div class="hero-img-wrap">
            <img
              src="assets/images/hero/hero-shape-7.png"
              alt="Image"
              class="hero-shape-one"
            />
            <img
              src="assets/images/hero/hero-shape-8.png"
              alt="Image"
              class="hero-shape-two"
            />
            <img
              src="assets/images/hero/hero-img-2.png"
              alt="Image"
              class="hero-img"
            />
          </div>
        </div>
        <div class="col-md-6">
          <div class="hero-content" data-speed="0.10" data-revert="true">
            <img
              src="assets/images/hero/hero-shape-6.png"
              alt="Image"
              class="hero-shape-four animationFramesTwo"
            />
            <img
              src="assets/images/hero/hero-shape-5.png"
              alt="Image"
              class="hero-shape-five bounce"
            />
            <span>Instant Money Transfer</span>
            <h3 style="color: aliceblue;">Online Banking Pay In Your Hand</h3>
            <p>
              Welcome to NORAH, your trusted partner in online payment solutions. With a relentless commitment to secure, seamless, and efficient transactions, we empower businesses of all sizes to thrive in the digital world.
            </p>

            <router-link to="/login" class="btn style1"
              >Get Started</router-link
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "MainBanner",
};
</script>
