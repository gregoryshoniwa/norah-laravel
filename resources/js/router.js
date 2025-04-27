import { createRouter, createWebHistory } from 'vue-router';
import Home from './components/Home.vue';
import Login from './components/Login.vue';
import Register from './components/Register.vue';
import Dashboard from './components/Dashboard.vue';
import Users from './components/pages/Users.vue';
import Merchants from './components/pages/Merchants.vue';
import ResetPassword from './components/ResetPassword.vue';
import ConfirmAccount from './components/ConfirmAccount.vue';
import CheckOut from './components/CheckOut.vue';
import RecoverPassword from './components/RecoverPassword.vue';
import CheckOutLoading from './components/CheckOutLoading.vue';

const routes = [
    { path: '/', component: Home },
    { path: '/login', component: Login },
    { path: '/register', component: Register },
    {
      path: '/dashboard',
      component: Dashboard,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('./components/pages/DashboardHome.vue')
        },
        {
          path: 'users',
          name: 'users',
          component: Users
        },
        {
          path: 'merchants',
          name: 'merchants',
          component: Merchants
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('./components/pages/Settings.vue')
        },
        {
          path: 'transactions',
          name: 'transactions',
          component: () => import('./components/pages/Transactions.vue')
        }
      ]
    },
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
    const publicPages = ['/login', '/register', '/reset-password', '/confirm-account', '/recover-password', '/check-out', '/check-out-loading', '/'];
    const authRequired = !publicPages.includes(to.path);
    const token = localStorage.getItem('authToken');

    // Check if route requires auth and user is not logged in
    if (authRequired && !token) {
        localStorage.removeItem('authToken'); // Clear any invalid token
        localStorage.removeItem('user'); // Clear user data
        return next('/login');
    }

    // If user is logged in and tries to access login/register pages
    if (token && ['/login', '/register'].includes(to.path)) {
        return next('/dashboard');
    }

    // If token exists, verify it's not expired
    if (token) {
        try {
            const tokenData = JSON.parse(atob(token.split('.')[1]));
            if (tokenData.exp * 1000 < Date.now()) {
                // Token is expired
                localStorage.removeItem('authToken');
                localStorage.removeItem('user');
                return next('/login');
            }
        } catch (e) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('user');
            return next('/login');
        }
    }

    next();
});

export default router;
