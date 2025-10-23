<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import MoneyInput from '@/Components/Form/MoneyInput.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';

const props = defineProps<{
  show: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'created', payload: unknown): void;
}>();

const tipoOptions = ['conta_corrente', 'poupanca', 'investimento', 'caixa', 'outro'] as const;
const categoriaOptions = ['operacional', 'reserva', 'investimento'] as const;

type TipoOption = (typeof tipoOptions)[number];
type CategoriaOption = (typeof categoriaOptions)[number];

const defaultForm = () => ({
  nome: '',
  apelido: '',
  tipo: 'conta_corrente' as TipoOption,
  instituicao: '',
  banco: '',
  agencia: '',
  numero: '',
  carteira: '',
  moeda: 'BRL',
  saldo_inicial: '0.00',
  data_saldo_inicial: '',
  limite_credito: '',
  categoria: 'operacional' as CategoriaOption,
  permite_transf: true,
  padrao_recebimento: false,
  padrao_pagamento: false,
  observacoes: '',
  ativo: true,
});

const form = reactive(defaultForm());
const errors = reactive<Record<string, string>>({});
const submitting = ref(false);
const formError = ref('');
const toast = useToast();

const resetForm = () => {
  Object.assign(form, defaultForm());
  Object.keys(errors).forEach((key) => delete errors[key as keyof typeof errors]);
  formError.value = '';
};

watch(
  () => props.show,
  (value) => {
    if (value) {
      resetForm();
    }
  }
);

const close = () => {
  if (submitting.value) {
    return;
  }

  emit('close');
};

const submit = async () => {
  if (submitting.value) {
    return;
  }

  submitting.value = true;
  Object.keys(errors).forEach((key) => delete errors[key as keyof typeof errors]);
  formError.value = '';

  try {
    const payload = {
      nome: form.nome,
      tipo: form.tipo,
      apelido: form.apelido || null,
      instituicao: form.instituicao || null,
      banco: form.banco || null,
      agencia: form.agencia || null,
      numero: form.numero || null,
      carteira: form.carteira || null,
      moeda: form.moeda ? form.moeda.toUpperCase() : 'BRL',
      saldo_inicial: form.saldo_inicial === '' ? 0 : Number(form.saldo_inicial),
      data_saldo_inicial: form.data_saldo_inicial || null,
      limite_credito: form.limite_credito === '' ? null : Number(form.limite_credito),
      categoria: form.categoria,
      permite_transf: Boolean(form.permite_transf),
      padrao_recebimento: Boolean(form.padrao_recebimento),
      padrao_pagamento: Boolean(form.padrao_pagamento),
      observacoes: form.observacoes || null,
      ativo: form.ativo,
    };

    const response = await axios.post('/api/financeiro/accounts', payload);

    toast.success(response.data?.message ?? 'Conta financeira criada com sucesso.');
    emit('created', response.data);
    resetForm();
  } catch (error) {
    const axiosError = error as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;

    if (axiosError.response?.status === 422) {
      const validation = axiosError.response.data?.errors ?? {};
      Object.entries(validation).forEach(([key, messages]) => {
        errors[key as keyof typeof errors] = Array.isArray(messages)
          ? messages[0]
          : String(messages);
      });
      formError.value =
        axiosError.response.data?.message ?? 'Corrija os campos destacados e tente novamente.';
      return;
    }

    const message =
      axiosError.response?.data?.message ?? 'Não foi possível salvar a conta. Tente novamente.';
    formError.value = message;
    toast.error(message);
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="close"
    >
      <div
      class="relative w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40"
    >
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Nova conta financeira</h2>
            <p class="text-xs text-slate-400">Preencha os dados abaixo para cadastrar a conta.</p>
          </div>
          <button
            type="button"
            class="rounded-md p-2 text-slate-400 transition hover:text-white"
            @click="close"
          >
            <span class="sr-only">Fechar</span>
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>
        <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
          <div
            v-if="formError"
            class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-100"
          >
            {{ formError }}
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="flex flex-col gap-1 md:col-span-2">
              <label class="text-sm font-medium text-slate-200">Nome da conta *</label>
              <input
                v-model="form.nome"
                type="text"
                required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Banco XP - PJ"
              />
              <p v-if="errors.nome" class="text-xs text-rose-400">{{ errors.nome }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Apelido</label>
              <input
                v-model="form.apelido"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Conta operacional"
              />
              <p v-if="errors.apelido" class="text-xs text-rose-400">{{ errors.apelido }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Instituição</label>
              <input
                v-model="form.instituicao"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Banco XP"
              />
              <p v-if="errors.instituicao" class="text-xs text-rose-400">{{ errors.instituicao }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Banco</label>
              <input
                v-model="form.banco"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Banco 001"
              />
              <p v-if="errors.banco" class="text-xs text-rose-400">{{ errors.banco }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Agência</label>
              <input
                v-model="form.agencia"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="0001-9"
              />
              <p v-if="errors.agencia" class="text-xs text-rose-400">{{ errors.agencia }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Número</label>
              <input
                v-model="form.numero"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="12345-6"
              />
              <p v-if="errors.numero" class="text-xs text-rose-400">{{ errors.numero }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Carteira/variação</label>
              <input
                v-model="form.carteira"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <p v-if="errors.carteira" class="text-xs text-rose-400">{{ errors.carteira }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Tipo *</label>
              <select
                v-model="form.tipo"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option v-for="option in tipoOptions" :key="option" :value="option">
                  {{
                    {
                      conta_corrente: 'Conta corrente',
                      poupanca: 'Poupança',
                      investimento: 'Investimento',
                      caixa: 'Caixa',
                      outro: 'Outro',
                    }[option]
                  }}
                </option>
              </select>
              <p v-if="errors.tipo" class="text-xs text-rose-400">{{ errors.tipo }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Categoria *</label>
              <select
                v-model="form.categoria"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option v-for="option in categoriaOptions" :key="option" :value="option">
                  {{
                    {
                      operacional: 'Operacional',
                      reserva: 'Reserva',
                      investimento: 'Investimento',
                    }[option]
                  }}
                </option>
              </select>
              <p v-if="errors.categoria" class="text-xs text-rose-400">{{ errors.categoria }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Moeda</label>
              <input
                v-model="form.moeda"
                type="text"
                maxlength="3"
                class="w-full uppercase rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <p v-if="errors.moeda" class="text-xs text-rose-400">{{ errors.moeda }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Saldo inicial *</label>
              <MoneyInput
                v-model="form.saldo_inicial"
                name="saldo_inicial"
                :required="true"
                placeholder="0,00"
                :input-class="'border-slate-700 bg-slate-900 text-white'"
              />
              <p v-if="errors.saldo_inicial" class="text-xs text-rose-400">
                {{ errors.saldo_inicial }}
              </p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Data do saldo</label>
              <DatePicker
                v-model="form.data_saldo_inicial"
                placeholder="dd/mm/aaaa"
                appearance="dark"
              />
              <p v-if="errors.data_saldo_inicial" class="text-xs text-rose-400">
                {{ errors.data_saldo_inicial }}
              </p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Limite de crédito</label>
              <MoneyInput
                v-model="form.limite_credito"
                name="limite_credito"
                placeholder="0,00"
                :input-class="'border-slate-700 bg-slate-900 text-white'"
              />
              <p v-if="errors.limite_credito" class="text-xs text-rose-400">
                {{ errors.limite_credito }}
              </p>
            </div>
            <div class="flex flex-col gap-1 md:col-span-2">
              <label class="text-sm font-medium text-slate-200">Observações</label>
              <textarea
                v-model="form.observacoes"
                rows="3"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <p v-if="errors.observacoes" class="text-xs text-rose-400">
                {{ errors.observacoes }}
              </p>
            </div>
          </div>
          <div class="grid gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-4 md:grid-cols-2">
            <label class="inline-flex items-center gap-3 text-sm text-slate-200">
              <input
                v-model="form.permite_transf"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
              />
              Permite transferências
            </label>
            <label class="inline-flex items-center gap-3 text-sm text-slate-200">
              <input
                v-model="form.padrao_recebimento"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
              />
              Conta padrão de recebimento
            </label>
            <label class="inline-flex items-center gap-3 text-sm text-slate-200">
              <input
                v-model="form.padrao_pagamento"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
              />
              Conta padrão de pagamento
            </label>
            <label class="inline-flex items-center gap-3 text-sm text-slate-200">
              <input
                v-model="form.ativo"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
              />
              Conta ativa
            </label>
          </div>
          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
              @click="close"
              :disabled="submitting"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
              :disabled="submitting"
            >
              <svg
                v-if="submitting"
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M12 4v2" />
                <path d="M18.364 5.636l-1.414 1.414" />
                <path d="M20 12h-2" />
                <path d="M18.364 18.364l-1.414-1.414" />
                <path d="M12 20v-2" />
                <path d="M5.636 18.364l1.414-1.414" />
                <path d="M4 12h2" />
                <path d="M5.636 5.636l1.414 1.414" />
              </svg>
              Salvar
            </button>
          </div>
        </form>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
