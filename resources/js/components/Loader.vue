<template>
    <div class="loader loader-overlay">
        <div></div>
        <div></div>
        <div></div>
    </div>
</template>

<script>
export default {
    name: 'Loader'
}
</script>
<style scoped>
.loader-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.603);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 99999;
}

</style>