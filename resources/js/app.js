import './bootstrap';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';
import { installAxiosSessionGuard, registerRouter } from './auth/sessionGuard';
import '../css/app.css';
// import '../custom.scss';
import AOS from "aos";
import BootstrapVue3 from "bootstrap-vue-3";

import "aos/dist/aos.css";
import "bootstrap/dist/css/bootstrap.css";
import "bootstrap-vue-3/dist/bootstrap-vue-3.css";
import "bootstrap-icons/font/bootstrap-icons.css";

import "../custom.scss";

import VueSweetalert2 from 'vue-sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

import VueApexCharts from 'vue3-apexcharts';




const app = createApp(App);
app.use(router);

app.use(VueSweetalert2);

app.use(VueApexCharts);


app.use(BootstrapVue3);

// Install the session guard before mount so any boot-time 401 is caught.
registerRouter(router);
installAxiosSessionGuard();

// Initialize AOS after mounting
app.mount('#app');
AOS.init();
