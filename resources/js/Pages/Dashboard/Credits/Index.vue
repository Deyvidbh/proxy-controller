<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  balance: Number,
  total_cost: Number,
  cost_per_port: Number,
  transactions: {
    type: Array,
    default: () => [],
  },
  credits_summary: {
    type: Object,
    default: () => ({}),
  },
  ports: Array,
});

const notification = computed(() => {
  const flash = usePage().props.flash;
  if (flash?.success) {
    return { show: true, type: 'success', message: flash.success };
  }
  if (flash?.error) {
    return { show: true, type: 'error', message: flash.error };
  }
  return { show: false };
});

const feedbackMessage = ref(null);
const feedbackSuccess = ref(false);

setTimeout(() => {
  feedbackMessage.value = null;
}, 6000);

const renewalSummary = computed(() => {
  const portCount = props.ports.length;
  const costPerPort = portCount >= 20 ? 66 : 70;
  const totalCost = portCount * costPerPort;
  return {
    portCount,
    costPerPort,
    totalCost,
    hasEnoughCredits: props.balance >= totalCost,
  };
});

const renewingAll = ref(false);

const renewAllPorts = () => {
  renewingAll.value = true;
  feedbackMessage.value = null;

  router.post(
    route('dashboard.credits.store'),
    {},
    {
      preserveScroll: true,
      onSuccess: (page) => {
        const flash = page?.props?.flash || {};
        if (flash.error) {
          feedbackSuccess.value = false;
          feedbackMessage.value = flash.error;   // veio do catch do controller
        } else if (flash.success) {
          feedbackSuccess.value = true;
          feedbackMessage.value = flash.success; // sucesso
        } else {
          feedbackMessage.value = null;          // nada para mostrar
        }
      },
      onFinish: () => {
        renewingAll.value = false;
      },
    }
  );
};


const formatCurrency = (value) => parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const formatDate = (dateString) => new Date(dateString).toLocaleString('pt-BR');
const getTypeClass = (type) => type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
const getStatusClass = (status) => {
  switch (status) {
    case 'completed': return 'bg-green-100 text-green-800';
    case 'pending': return 'bg-blue-100 text-blue-800';
    default: return 'bg-red-100 text-red-800';
  }
};
</script>

<template>
  <AppLayout title="Meus Créditos">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Gestão de Créditos
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl sm:rounded-lg p-6">

          <div v-if="$page.props.flash?.message"
            class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
            <p>{{ $page.props.flash?.message }}</p>
          </div>

          <div class="bg-white shadow-md rounded-lg p-6 mb-6 border border-gray-200">

            <p class="text-sm text-gray-700">Você possui <strong>{{ renewalSummary.portCount }}</strong> portas
              ativas.
            </p>

            <p class="text-sm text-gray-700">Custo por porta: <strong>R$ {{ cost_per_port }}</strong></p>

            <p class="text-sm text-gray-700">Custo total da renovação: <strong>R$ {{ total_cost }}</strong></p>

            <button :disabled="renewingAll" @click="renewAllPorts"
              class="mt-4 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
              {{ renewingAll ? 'Gerando pagamento...' : 'Renovar Serviço' }}
            </button>

          </div>

          <div v-if="notification.show"
            :class="notification.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
            class="p-4 mb-4 text-sm rounded-lg" role="alert">
            <span class="font-medium">{{ notification.type === 'success' ? 'Sucesso!' : 'Erro!' }}</span>
            {{ notification.message }}
          </div>

          <div>
            <h4 class="text-lg font-semibold mb-4">Histórico de Créditos</h4>
            <div class="overflow-x-auto">
              <table class="min-w-full bg-white shadow rounded-lg">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">ID</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Preço</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Créditos</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Tipo</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Status</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Descrição</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Link Pag.</th>
                    <th class="py-2 px-4 border-b text-left text-sm font-medium">Atualizado em</th>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-if="props.transactions.length === 0">
                    <td colspan="8" class="text-center py-4 text-gray-500">Nenhuma transação encontrada.</td>
                  </tr>
                  <tr v-for="tx in props.transactions" :key="tx.external_reference" class="border-t">
                    <td class="py-2 px-4 border-b text-sm">{{ tx.external_reference }}</td>
                    <td class="py-2 px-4 border-b text-sm">{{ formatCurrency(tx.price) }}</td>
                    <td class="py-2 px-4 border-b text-sm">{{ tx.amount }}</td>
                    <td class="py-2 px-4 border-b text-sm">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                        :class="getTypeClass(tx.type)">
                        {{ tx.type }}
                      </span>
                    </td>
                    <td class="py-2 px-4 border-b text-sm">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                        :class="getStatusClass(tx.status)">
                        {{ tx.status }}
                      </span>
                    </td>
                    <td class="py-2 px-4 border-b text-sm">{{ tx.description }}</td>
                    <td class="py-2 px-4 border-b text-sm">
                      <a v-if="tx.init_point" :href="tx.init_point"
                        class="px-2 py-1 text-white bg-green-500 rounded hover:bg-green-600" target="_blank">PAGAR</a>
                    </td>
                    <td class="py-2 px-4 border-b text-sm">{{ formatDate(tx.updated_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
