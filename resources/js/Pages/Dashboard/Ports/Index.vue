<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircleIcon, XCircleIcon, ClockIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/solid';
import { router } from '@inertiajs/vue3'; // Importe o router do Inertia

// Props recebidas do controller
const props = defineProps({
    ports: Array,
});

// Estados reativos
const testResults = ref({});
const passwordVisible = ref({}); // Controla a visibilidade de cada senha
const renovationLoading = ref({}); // Controla o loading de cada switch

// Função para mostrar tempo restante
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

// Função para testar o proxy
const testProxy = async (port) => {
    testResults.value[port.id] = { loading: true, message: null, success: false };
    try {
        const response = await axios.post(route('dashboard.ports.test', { port: port.id }));
        testResults.value[port.id] = { loading: false, message: `OK! IP de Saída: ${response.data.ip}`, success: true };
    } catch (error) {
        const errorMessage = error.response?.data?.error || 'Ocorreu um erro desconhecido.';
        testResults.value[port.id] = { loading: false, message: `Falha: ${errorMessage}`, success: false };
    }
};

// Função para alternar visibilidade da senha
const togglePasswordVisibility = (portId) => {
    passwordVisible.value[portId] = !passwordVisible.value[portId];
};

// Função para alternar a auto-renovação
const toggleAutoRenovation = async (port) => {
    renovationLoading.value[port.id] = true;
    const newStatus = !port.auto_renovation;

    // Usamos o router do Inertia para fazer o PATCH, pois ele atualiza as props automaticamente
    router.patch(route('dashboard.ports.toggle-renovation', { port: port.id }), {
        auto_renovation: newStatus,
    }, {
        preserveState: true, // Mantém o estado da página (resultados dos testes, etc)
        preserveScroll: true, // Mantém a posição do scroll
        onFinish: () => {
            renovationLoading.value[port.id] = false;
        },
        onError: (errors) => {
            // Você pode adicionar um tratamento de erro mais robusto aqui (ex: um toast)
            console.error("Erro ao atualizar a renovação:", errors);
        }
    });
};


// Computada para exemplos
const firstPort = computed(() => props.ports.length > 0 ? props.ports[0] : null);

</script>

<template>
    <AppLayout title="Portas Proxy">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Portas Proxy 🌐</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div v-if="$page.props.flash?.message"
                    class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p>{{ $page.props.flash?.message }}</p>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                </div>

            </div>
        </div>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                        IP Autorizado</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Auto-Renovação</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="ports.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">Você ainda não possui
                                        portas
                                        proxy.</td>
                                </tr>
                                <tr v-for="port in ports" :key="port.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ port.host }}:{{ port.port }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">Usuário: <span
                                                class="font-mono bg-gray-100 px-1 rounded">{{ port.username }}</span>
                                        </div>
                                        <div class="text-sm text-gray-500 flex items-center">
                                            Senha:
                                            <span class="font-mono bg-gray-100 px-1 rounded ml-1 mr-2">
                                                {{ passwordVisible[port.id] ? port.password : '••••••••' }}
                                            </span>
                                            <button @click="togglePasswordVisibility(port.id)"
                                                class="text-gray-400 hover:text-gray-600">
                                                <EyeIcon v-if="!passwordVisible[port.id]" class="h-5 w-5" />
                                                <EyeSlashIcon v-else class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-mono">{{
                                        port.ip_address
                                        }}</td>
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
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button @click="toggleAutoRenovation(port)" type="button"
                                            :disabled="renovationLoading[port.id]"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 disabled:opacity-50"
                                            :class="port.auto_renovation ? 'bg-indigo-600' : 'bg-gray-200'"
                                            role="switch" :aria-checked="port.auto_renovation">
                                            <span
                                                class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                :class="{ 'translate-x-5': port.auto_renovation, 'translate-x-0': !port.auto_renovation }">
                                                <span
                                                    class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                                                    :class="{ 'opacity-0 ease-out duration-100': port.auto_renovation, 'opacity-100 ease-in duration-200': !port.auto_renovation }"
                                                    aria-hidden="true">
                                                    <svg v-if="!renovationLoading[port.id]"
                                                        class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                        <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 4" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                </span>
                                                <span
                                                    class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                                                    :class="{ 'opacity-100 ease-in duration-200': port.auto_renovation, 'opacity-0 ease-out duration-100': !port.auto_renovation }"
                                                    aria-hidden="true">
                                                    <svg v-if="!renovationLoading[port.id]"
                                                        class="h-3 w-3 text-indigo-600" fill="currentColor"
                                                        viewBox="0 0 12 12">
                                                        <path
                                                            d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />
                                                    </svg>
                                                </span>
                                                <svg v-if="renovationLoading[port.id]"
                                                    class="animate-spin absolute inset-0 m-auto h-4 w-4 text-indigo-500"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
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

                <div v-if="firstPort" class="mt-8 bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Como Usar o Proxy (Exemplos)</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Para testar sua conexão via linha de comando, você pode usar o `curl`. Substitua os valores
                        pelos dados
                        da sua porta. Abaixo, usamos os dados da sua primeira porta como exemplo.
                    </p>
                    <div class="bg-gray-800 text-white p-4 rounded-md text-sm font-mono">
                        <p class="mb-2"># Comando para testar com autenticação</p>
                        <code class="block whitespace-pre-wrap">curl --proxy http://{{ firstPort.username }}:{{
                            firstPort.password }}@{{ firstPort.host }}:{{ firstPort.port }} https://ipv4.icanhazip.com</code>
                        <p class="mt-4 mb-2 text-gray-400"># Alternativa de comando</p>
                        <code class="block whitespace-pre-wrap">curl -x http://{{ firstPort.host }}:{{ firstPort.port }}
                    --proxy-user {{ firstPort.username }}:{{ firstPort.password }} https://ipv4.icanhazip.com</code>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">
                        O comando acima deve retornar o seu IP Autorizado: <span
                            class="font-mono bg-gray-100 p-1 rounded">{{
                                firstPort.ip_address }}</span>. Se retornar outro IP ou um erro, verifique se o IP da sua
                        máquina de
                        teste está corretamente configurado como "IP Autorizado".
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>