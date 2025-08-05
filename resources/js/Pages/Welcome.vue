<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>

    <Head title="Proxy BCopy" />

    <div class="min-h-screen bg-black text-white flex items-center justify-center px-6 py-12">
        <div class="max-w-2xl w-full text-center space-y-10">
            <!-- Logo -->
            <div class="flex justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 1024 1024" fill="white">
                    <path
                        d="M4805 9449c-683-52-1292-237-1875-572-480-275-921-664-1257-1108-620-818-925-1806-873-2822 32-620 172-1160 441-1699 229-458 511-845 875-1197 717-695 1646-1116 2649-1201 158-13 571-13 732 1 787 65 1531 344 2183 818 385 279 754 660 1023 1056 415 612 663 1309 728 2050 16 191 14 599-6 800-59 610-243 1191-550 1730-519 914-1359 1600-2363 1931-530 175-1162 254-1707 213z" />
                </svg>
            </div>

            <!-- Título -->
            <h1 class="text-4xl font-bold sm:text-5xl">
                Proxy Residencial Ilimitado
            </h1>

            <!-- Descrição -->
            <p class="text-lg text-white/70">
                O proxy da <strong class="text-white">BCopy</strong> entrega IPs limpos com alta estabilidade e
                velocidade. Ideal para automações, privacidade e marketing.
            </p>

            <!-- Botões -->
            <div class="flex justify-center gap-4">
                <a href="/login"
                    class="bg-white text-black px-6 py-3 rounded-lg font-semibold shadow hover:bg-gray-200 transition">
                    Login
                </a>
                <a href="/register"
                    class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition">
                    Criar Conta
                </a>
            </div>
        </div>
    </div>
</template>
