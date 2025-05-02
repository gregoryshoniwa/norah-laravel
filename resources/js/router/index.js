import { createRouter, createWebHistory } from 'vue-router';
import Login from '../components/Login.vue';
import Register from '../components/Register.vue';
import PrivacyPolicy from '../components/PrivacyPolicy.vue';
import Home from '../components/Home.vue';

const routes = [
  {
    path: '/',
    component: Home,
    meta: { requiresAuth: false }
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresAuth: false }
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresAuth: false }
  },
  {
    path: '/privacy-policy',
    name: 'PrivacyPolicy',
    component: PrivacyPolicy,
    meta: { requiresAuth: false }
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    } else {
      return { top: 0 };
    }
  }
});

router.beforeEach((to, from, next) => {
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth !== false);
  const loggedIn = localStorage.getItem('user-token');

  if (requiresAuth && !loggedIn) {
    next('/login');
  } else {
    next();
  }
});

export default router;
