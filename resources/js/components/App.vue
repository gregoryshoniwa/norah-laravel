<template>
  <div>
    <Preloader v-if="isLoading" />
    <router-view v-slot="{ Component }">
      <transition
        enter-active-class="animated fadeIn"
        leave-active-class="animated fadeOut"
        mode="out-in"
        :duration="600"
        @after-leave="$root.$emit('triggerScroll')"
      >
        <component :is="Component" />
      </transition>
    </router-view>
    <BackToTop />
  </div>
</template>

<script>
import Preloader from "./Preloader.vue";
import BackToTop from "./BackToTop.vue";

export default {
  name: "App",
  components: {
    Preloader,
    BackToTop
  },
  data() {
    return {
      isLoading: true
    };
  },
  mounted() {
    setTimeout(() => {
      this.isLoading = false;
    }, 2000);
  }
};
</script>
