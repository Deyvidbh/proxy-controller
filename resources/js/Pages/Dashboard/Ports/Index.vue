<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircleIcon, XCircleIcon, ClockIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/solid';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    ports: Array,
    user: Object,
});

const rotating = ref({});
const rotatingAll = ref(false);

const rotateIp = async (port) => {
    rotating.value[port.id] = true;
    try {
        const response = await axios.post(route('dashboard.ports.rotate', { port: port.id }));
        const updated = response.data;

        // Atualiza IP visualmente
        const updatedPort = props.ports.find(p => p.id === updated.portId);
        if (updatedPort) {
            updatedPort.output_ip_address = updated.newOutputIp;
        }

        testResults.value[port.id] = {
            loading: false,
            message: `IP rotacionado com sucesso: ${updated.newOutputIp}`,
            success: true
        };
    } catch (error) {
        testResults.value[port.id] = {
            loading: false,
            message: error.response?.data?.error || 'Erro ao rotacionar IP.',
            success: false
        };
    } finally {
        rotating.value[port.id] = false;
    }
};

const rotateAllIps = async () => {
    rotatingAll.value = true;
    try {
        const response = await axios.post(route('dashboard.ports.rotate-all'));
        const updatedPorts = response.data.updatedPorts || [];
        const skippedPorts = response.data.skippedPorts || [];

        for (const updated of updatedPorts) {
            const port = props.ports.find(p => p.id === updated.portId);
            if (port) {
                port.output_ip_address = updated.newOutputIp;
                testResults.value[port.id] = {
                    loading: false,
                    message: `IP rotacionado para ${updated.newOutputIp}`,
                    success: true
                };
            }
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        for (const skipped of skippedPorts) {
            const port = props.ports.find(p => p.id === skipped.portId);
            if (port) {
                testResults.value[port.id] = {
                    loading: false,
                    message: skipped.reason,
                    success: false
                };
            }
            await new Promise(resolve => setTimeout(resolve, 300));
        }
    } catch (error) {
        console.error('Erro ao rotacionar IPs:', error);
    } finally {
        rotatingAll.value = false;
    }
};

const testResults = ref({});
const passwordVisible = ref({});
const renovationLoading = ref({ global: false });
const userCredits = ref(props.user.credits_balance);

const formatTimeRemaining = (expiryDate) => {
    if (!expiryDate) return 'N/A';
    const now = new Date();
    const expires = new Date(expiryDate);
    const diff = expires.getTime() - now.getTime();
    if (diff <= 0) return 'Expirado';
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    if (days > 0) return `Expira em ${days} dia${days > 1 ? 's' : ''}`;
    return `Expira em ${hours} hora${hours > 1 ? 's' : ''}`;
};

const testProxy = async (port) => {
    testResults.value[port.id] = { loading: true, message: null, success: false };
    try {
        const response = await axios.post(route('dashboard.ports.test', { port: port.id }));
        testResults.value[port.id] = {
            loading: false,
            message: `OK! IP de Saída: ${response.data.ip}`,
            success: true
        };
    } catch (error) {
        const errorMessage = error.response?.data?.error || 'Ocorreu um erro desconhecido.';
        testResults.value[port.id] = {
            loading: false,
            message: `Falha: ${errorMessage}`,
            success: false
        };
    }
};

const togglePasswordVisibility = (portId) => {
    passwordVisible.value[portId] = !passwordVisible.value[portId];
};

const toggleAutoRenovation = async () => {
    renovationLoading.value.global = true;
    const newStatus = !props.user.auto_renovation;

    router.patch(route('dashboard.ports.toggle-renovation'), {
        auto_renovation: newStatus,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            renovationLoading.value.global = false;
        },
        onError: (errors) => {
            console.error("Erro ao atualizar a renovação:", errors);
        }
    });
};

const feedbackMessage = ref(null);
const feedbackSuccess = ref(false);

const renewingAll = ref(false);
const renewalSummary = computed(() => {
    const portCount = props.ports.length;
    const costPerPort = portCount >= 20 ? 66 : 70;
    const totalCost = portCount * costPerPort;
    return {
        portCount,
        costPerPort,
        totalCost,
        hasEnoughCredits: props.user.credits_balance >= totalCost,
    };
});

const renewAllPorts = async () => {
    renewingAll.value = true;
    feedbackMessage.value = null;

    try {
        const response = await axios.post(route('dashboard.ports.renew-all'));

        feedbackSuccess.value = true;
        feedbackMessage.value = response.data.message;

        const renewedPorts = response.data.renewedPorts || [];

        for (const renewed of renewedPorts) {
            const port = props.ports.find(p => p.id === renewed.id);
            if (port) {
                port.expires_at = renewed.expires_at;
                port.last_renovation = renewed.last_renovation;
                port.active_license = true;
            }
        }

        if (response.data.newCreditsBalance !== undefined) {
            userCredits.value = response.data.newCreditsBalance;
        }

    } catch (error) {
        feedbackSuccess.value = false;
        feedbackMessage.value = error.response?.data?.message || 'Erro ao renovar portas.';
    } finally {
        renewingAll.value = false;
    }
};

setTimeout(() => {
    feedbackMessage.value = null;
}, 6000);

</script>

<template>
    <AppLayout title="Portas Proxy">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Portas Proxy 🌐</h2>
                <div class="flex items-center space-x-3">
                    <!-- <span class="text-sm text-gray-600">Auto-Renovação:</span> -->
                    <!-- <button @click="toggleAutoRenovation" :disabled="renovationLoading.global"
                        class="mr-2 relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                        :class="props.user.auto_renovation ? 'bg-indigo-600' : 'bg-gray-300'" role="switch"
                        :aria-checked="props.user.auto_renovation">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                            :class="props.user.auto_renovation ? 'translate-x-5' : 'translate-x-0'">
                        </span>
                    </button> -->
                    <button @click="rotateAllIps" :disabled="rotatingAll"
                        class="mr-2 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                        <span v-if="rotatingAll">Rotacionando IPs...</span>
                        <span v-else>Rotacionar Todos os IPs</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div v-if="feedbackMessage" class="mb-4">
                    <div :class="feedbackSuccess ? 'bg-green-100 border-green-500 text-green-800' : 'bg-red-100 border-red-500 text-red-800'"
                        class="border-l-4 p-4 rounded" role="alert">
                        {{ feedbackMessage }}
                    </div>
                </div>

                <div v-if="$page.props.flash?.message"
                    class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p>{{ $page.props.flash?.message }}</p>
                </div>

                <div class="bg-white shadow-md rounded-lg p-6 mb-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Renovação de Portas</h3>
                    <p class="text-sm text-gray-700">Você possui <strong>{{ renewalSummary.portCount }}</strong> portas
                        ativas.
                    </p>
                    <p class="text-sm text-gray-700">Custo por porta: <strong>{{ renewalSummary.costPerPort }}
                            créditos</strong>
                    </p>
                    <p class="text-sm text-gray-700">Custo total da renovação: <strong>{{ renewalSummary.totalCost }}
                            créditos</strong></p>
                    <p class="text-sm text-gray-700">Seu saldo: <strong>{{ userCredits }} créditos</strong></p>

                    <button :disabled="renewingAll || !renewalSummary.hasEnoughCredits" @click="renewAllPorts"
                        class="mt-4 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
                        {{ renewingAll ? 'Renovando...' : 'Renovar Todas as Portas' }}
                    </button>
                </div>


                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Porta / Host</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Credenciais</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        IP de Saída</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="ports.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Você ainda não possui
                                        portas
                                        proxy.</td>
                                </tr>
                                <tr v-for="port in ports" :key="port.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ port.host }}:{{ port.port }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">Usuário:
                                            <span class="font-mono bg-gray-100 px-1 rounded">{{
                                                props.user.squid_username
                                            }}</span>
                                        </div>
                                        <div class="text-sm text-gray-500 flex items-center">
                                            Senha:
                                            <span class="font-mono bg-gray-100 px-1 rounded ml-1 mr-2">
                                                {{ passwordVisible[port.id] ? props.user.squid_password : '••••••••' }}
                                            </span>
                                            <button @click="togglePasswordVisibility(port.id)"
                                                class="text-gray-400 hover:text-gray-600">
                                                <EyeIcon v-if="!passwordVisible[port.id]" class="h-5 w-5" />
                                                <EyeSlashIcon v-else class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-mono">
                                        {{ port.output_ip_address || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="port.active_license"
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativa</span>
                                        <span v-else
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Expirada</span>
                                        <div class="text-xs text-gray-500 mt-1 flex items-center">
                                            <ClockIcon class="h-4 w-4 mr-1" /> {{ formatTimeRemaining(port.expires_at)
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="rotateIp(port)" :disabled="rotating[port.id]"
                                            class="mr-2 inline-flex items-center px-2 py-1.5 text-xs font-medium rounded-md shadow-sm bg-yellow-500 hover:bg-yellow-600 text-white ml-2 disabled:opacity-50">
                                            {{ rotating[port.id] ? 'Rotacionando...' : 'Rotacionar IP' }}
                                        </button>
                                        <button @click="testProxy(port)" :disabled="testResults[port.id]?.loading"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                            <span v-if="testResults[port.id]?.loading">Testando...</span>
                                            <span v-else>Testar Conexão</span>
                                        </button>
                                        <div v-if="testResults[port.id] && !testResults[port.id].loading"
                                            class="mt-2 text-xs flex items-center"
                                            :class="{ 'text-green-600': testResults[port.id].success, 'text-red-600': !testResults[port.id].success }">
                                            <CheckCircleIcon v-if="testResults[port.id].success" class="h-4 w-4 mr-1" />
                                            <XCircleIcon v-else class="h-4 w-4 mr-1" />
                                            {{ testResults[port.id].message }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- EXEMPLOS PARA TODAS AS PORTAS -->
                <div v-if="ports.length" class="mt-8 bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Como Usar o Proxy (Exemplos)</h3>
                    <p class="text-sm text-gray-600 mb-4">Abaixo estão exemplos de uso do `curl` para cada uma das suas
                        portas
                        proxy:</p>

                    <div v-for="port in ports" :key="'example-' + port.id"
                        class="mb-6 border border-gray-200 rounded p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Porta: {{ port.host }}:{{ port.port }}</h4>

                        <div class="bg-gray-800 text-white p-4 rounded-md text-sm font-mono">
                            <p class="mb-2"># Comando para testar com autenticação</p>
                            <code class="block whitespace-pre-wrap">
                        curl --proxy http://{{ props.user.squid_username }}:{{ props.user.squid_password }}@{{ port.host
                        }}:{{ port.port }} https://ipv4.icanhazip.com
                    </code>

                            <p class="mt-4 mb-2 text-gray-400"># Alternativa de comando</p>
                            <code class="block whitespace-pre-wrap">
                        curl -x http://{{ port.host }}:{{ port.port }} --proxy-user {{ props.user.squid_username }}:{{
                            props.user.squid_password }} https://ipv4.icanhazip.com
                    </code>
                        </div>

                        <p class="mt-2 text-xs text-gray-500">
                            IP de saída esperado:
                            <span class="font-mono bg-gray-100 p-1 rounded">{{ port.output_ip_address || 'N/A' }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
