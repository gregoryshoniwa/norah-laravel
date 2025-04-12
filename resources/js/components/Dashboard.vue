<template>
    <div class="dashboard-container">
        <header>
            <h1>Dashboard</h1>
            <button @click="handleLogout">Logout</button>
        </header>
        <main>
            <div class="user-info">
                <h2>Welcome, {{ user.name }}</h2>
                <p>Email: {{ user.email }}</p>
            </div>
            <!-- Add more admin features here -->
        </main>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'DashboardPage',
    data() {
        return {
            user: {}
        }
    },
    async created() {
        try {
            const response = await axios.get('/api/v1/user', {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('authToken')}`
                }
            });
            this.user = response.data;
        } catch (error) {
            console.error(error);
            this.$router.push('/login');
        }
    },
    methods: {
        async handleLogout() {
            try {
                await axios.post('/api/v1/logout', {}, {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('authToken')}`
                    }
                });
                localStorage.removeItem('authToken');
                this.$router.push('/login');
            } catch (error) {
                console.error(error);
            }
        }
    }
}
</script>

<style scoped>
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.user-info {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 5px;
}

button {
    padding: 8px 15px;
    background: #f44336;
    color: white;
    border: none;
    cursor: pointer;
}
</style>
