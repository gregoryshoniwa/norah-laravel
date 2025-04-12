import { createRouter, createWebHistory } from 'vue-router';
import Home from './components/Home.vue';
import Login from './components/Login.vue';
import Register from './components/Register.vue';
import Dashboard from './components/Dashboard.vue';
import ResetPassword from './components/ResetPassword.vue';
import ConfirmAccount from './components/ConfirmAccount.vue';
import CheckOut from './components/CheckOut.vue';
import RecoverPassword from './components/RecoverPassword.vue';
import CheckOutLoading from './components/CheckOutLoading.vue';

const routes = [
    { path: '/', component: Home },
    { path: '/login', component: Login },
    { path: '/register', component: Register },
    { path: '/dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/reset-password', component: ResetPassword },
    { path: '/confirm-account', component: ConfirmAccount },
    { path: '/check-out', component: CheckOut },
    { path: '/check-out-loading', component: CheckOutLoading },
    { path: '/recover-password',component: RecoverPassword}
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    if (to.meta.requiresAuth && !localStorage.getItem('authToken')) {
        next('/login');
    } else {
        next();
    }
});

export default router;
