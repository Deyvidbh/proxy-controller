<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// --- Props (Dados vindos diretamente do Controller Laravel) ---
const props = defineProps({
  balance: {
    type: Number,
    required: true,
  },
  transactions: {
    type: Array,
    default: () => [],
  },
});

// --- Estado Reativo da Página ---
const isSubmitting = ref(false);
const isModalOpen = ref(false);
const creditQuantity = ref(50); // Valor inicial do input

// --- Notificações ---
// Sistema de notificação reativo que lê as "flash messages" do Laravel.
const notification = computed(() => {
  const flash = usePage().props.flash;

  // Usamos o "?." para verificar se 'flash' existe antes de tentar acessar 'success' ou 'error'.
  // Se 'flash' for undefined, a expressão inteira retorna undefined (que é "falso") sem dar erro.
  if (flash?.success) {
    return { show: true, type: 'success', message: flash.success };
  }
  if (flash?.error) {
    return { show: true, type: 'error', message: flash.error };
  }
  return { show: false };
});

// --- Métodos ---
const addCredits = () => {
  router.post(route('dashboard.credits.create'), {
    quantity: creditQuantity.value,
  }, {
    preserveScroll: true,
    onStart: () => isSubmitting.value = true,
    onFinish: () => isSubmitting.value = false,
    onSuccess: () => {
      closeModal();
      // A notificação aparece automaticamente via `computed property`.
      // As props são atualizadas automaticamente pelo Inertia.
    },
    onError: (errors) => {
      if (errors.quantity) {
        alert('Erro: ' + errors.quantity); // Alerta simples para erro de validação
      }
    }
  });
};

// Funções do Modal
const openModal = () => isModalOpen.value = true;
const closeModal = () => {
  isModalOpen.value = false;
  creditQuantity.value = 50;
};

// --- Funções de Formatação (Helpers) ---
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
          <div class="mb-6 flex items-center justify-between">
            <button @click="openModal" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
              + Adicionar Créditos
            </button>
            <div class="bg-blue-100 text-blue-700 px-4 py-2 rounded-md shadow-sm">
              Saldo: <span class="font-bold">{{ props.balance }}</span> créditos
            </div>
          </div>

          <div v-if="notification.show"
            :class="notification.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
            class="p-4 mb-4 text-sm rounded-lg" role="alert">
            <span class="font-medium">{{ notification.type === 'success' ? 'Sucesso!' : 'Erro!' }}</span> {{
              notification.message }}
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

    <div v-if="isModalOpen"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="relative mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
          <h3 class="text-lg leading-6 font-medium text-gray-900">Adicionar Créditos</h3>
          <div class="mt-2 px-7 py-3">
            <form @submit.prevent="addCredits">
              <div class="mb-4">
                <label for="quantity" class="block text-sm font-medium text-gray-700 text-left">Quantidade de
                  créditos</label>
                <input v-model="creditQuantity" type="number" id="quantity"
                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                  required min="50" max="300">
              </div>
              <div class="flex justify-center">
                <button type="submit" :disabled="isSubmitting"
                  class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded inline-flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                  <span>Adicionar Créditos</span>
                  <span v-if="isSubmitting"
                    class="ml-2 animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-white"></span>
                </button>
              </div>
            </form>
          </div>
          <div class="items-center px-4 py-3">
            <button @click="closeModal"
              class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600">
              Fechar
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>