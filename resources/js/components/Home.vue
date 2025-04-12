<template>
    <div class="page-wrapper">
        <Navbar class="style2" />
        <MainBanner />
        <ProtectMoney />
        <PaymentPlatform />
        <Currency />
        <Services />
        <Footer />
    </div>
</template>

<script>
import Navbar from './home/Navbar.vue'
import MainBanner from './home/MainBanner.vue'
import Services from './home/Services.vue'
import Currency from './home/Currency.vue'
import ProtectMoney from './home/ProtectMoney.vue'
import PaymentPlatform from './home/PaymentPlatform.vue'
import Footer from './home/Footer.vue'

export default {
    name: 'Home',
    components: {
        Navbar,
        MainBanner,
        Services,
        Currency,
        ProtectMoney,
        Footer,
        PaymentPlatform
    }
}
</script>
