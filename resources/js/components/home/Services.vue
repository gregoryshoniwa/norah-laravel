<template>
    <div id="Services" class="service-wrap ptb-100  bg-stratos">
        <div class="container">
            <div class="section-title style1 text-center mb-40">
                <span>Our Services</span>
                <h2 class="text-white">Products and Services</h2>
                <p style="color: aliceblue;font-size: larger;">
                    At NORAH, we offer a comprehensive suite of payment solutions tailored to meet your specific needs. The products and services help businesses of all sizes accept online payments and manage their financial transactions. Our range of services includes.
                </p>
            </div>
            <div class="service-slider-one">
                <carousel
                    :autoplay="5000"
                    :settings='settings'
                    :breakpoints="breakpoints"
                >
                    <Slide v-for="slide in carouselItems" :key="slide.id">
                        <div class="service-card style2">
                            <div class="service-info">
                                <div class="service-title">
                                    <span><i :class="slide.icon"></i></span>
                                    <h3><router-link to="/">{{slide.title}}</router-link></h3>
                                </div>
                                <p>{{slide.desc}}</p>
                                <!-- <router-link to="/service-details" class="link style1">Learn More <i class="flaticon-right-arrow-1"></i></router-link> -->
                            </div>
                        </div>
                    </Slide>

                    <template #addons>
                        <Pagination />
                    </template>
                </carousel>
            </div>
        </div>
    </div>
</template>

<script>
import { defineComponent } from 'vue';
import { Carousel, Slide, Pagination  } from 'vue3-carousel';

import 'vue3-carousel/dist/carousel.css';

export default defineComponent ({
    name: 'Services',
    components: {
        Carousel,
        Slide,
        Pagination,
    },
    data: () => ({
        settings: {
            itemsToShow: 1,
            snapAlign: 'center',
        },
        carouselItems: [
            {
                id: 1,
                icon: 'flaticon-payment-method',
                title: 'Payment Gateway',
                desc: 'Seamlessly integrate our payment gateway into your website or app to enable smooth and secure online transactions. NORAH provides a secure and customizable payment gateway for seamless integration into websites, mobile apps, and e-commerce platforms.',
            },
            {
                id: 2,
                icon: 'flaticon-computer',
                title: 'Payment Processing',
                desc: ': Process credit card, debit card, and digital wallet payments with ease. NORAH enables businesses to accept a variety of payment methods, including credit cards, debit cards, digital wallets (e.g., Apple Pay, Google Pay), and international payment options.',
            },
            {
                id: 3,
                icon: 'flaticon-loan-1',
                title: 'Fraud Prevention',
                desc: 'Protect your business with advanced fraud detection and prevention tools. NORAH  uses machine learning to detect and prevent fraudulent transactions, enhancing security for businesses and customers.',
            },
            {
                id: 4,
                icon: 'flaticon-secure-shield',
                title: 'Reporting and Analytics',
                desc: 'Gain valuable insights into your transaction data with our robust reporting and analytics tools. Businesses can access detailed analytics and reporting to gain insights into their payment data and customer behavior.',
            },
            {
                id: 5,
                icon: 'flaticon-mortarboard',
                title: 'Payment Links',
                desc: 'Businesses can create and share payment links with customers to request payments or donations quickly and securely.',
            },
            {
                id: 6,
                icon: 'flaticon-loan',
                title: 'Global Reach',
                desc: 'Accept payments in multiple currencies and expand your customer base worldwide.',
            },
        ],
        breakpoints: {
            0: {
                itemsToShow: 1,
			},
            576: {
                itemsToShow: 1,
                snapAlign: 'center',
            },
            768: {
                itemsToShow: 2,
                snapAlign: 'center',
            },
            1200: {
                itemsToShow: 3,
                snapAlign: 'center',
            },
        },
    }),
})
</script>
