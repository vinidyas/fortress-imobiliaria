<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import DatePicker from '@/Components/Form/DatePicker.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

interface AccountOption {
  id: number;
  nome: string;
}

interface LedgerRow {
  id: number;
  movement_date?: string | null;
  due_date?: string | null;
  description?: string | null;
  type?: string | null;
  type_label?: string | null;
  person?: { id: number; nome: string } | null;
  property?: { id: number; nome: string } | null;
  notes?: string | null;
  reference_code?: string | null;
  status?: string | null;
  status_label?: string | null;
  signed_amount?: number;
}

const props = defineProps<{
  accounts: AccountOption[];
  canExport: boolean;
}>();

const STORAGE_KEY = 'reports:bank-ledger:filters';

const loadSavedFilters = () => {
  if (typeof window === 'undefined') {
    return {} as Record<string, string>;
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw) as Record<string, string>;
    return parsed ?? {};
  } catch (error) {
    console.error('Failed to parse saved filters', error);
    return {};
  }
};

const savedFilters = loadSavedFilters();

const filters = reactive({
  financial_account_id: (savedFilters.financial_account_id ?? '') as string | number,
  date_from: savedFilters.date_from ?? '',
  date_to: savedFilters.date_to ?? '',
  type: 'despesa',
  status: savedFilters.status ?? '',
});

const accountSelectRef = ref<HTMLSelectElement | null>(null);
const accountSelectWidth = ref('100%');

type DatePreset =
  | 'today'
  | 'yesterday'
  | 'tomorrow'
  | 'thisWeek'
  | 'lastWeek'
  | 'thisMonth'
  | 'thisYear';

const datePresets: Array<{ id: DatePreset; label: string }> = [
  { id: 'today', label: 'Hoje' },
  { id: 'yesterday', label: 'Ontem' },
  { id: 'tomorrow', label: 'Amanhã' },
  { id: 'thisWeek', label: 'Esta semana' },
  { id: 'lastWeek', label: 'Semana passada' },
  { id: 'thisMonth', label: 'Mês' },
  { id: 'thisYear', label: 'Este ano' },
];

const loading = ref(false);
const errorMessage = ref('');
const ledgerRows = ref<LedgerRow[]>([]);
const openingBalance = ref(0);
const closingBalance = ref(0);
const totals = ref<{ inflow: number; outflow: number; net: number }>({
  inflow: 0,
  outflow: 0,
  net: 0,
});

const reportAccountName = ref('Todos os bancos');

const columnWidths = computed(() => {
  const rows = ledgerRows.value ?? [];
  const maxLength = (selector: (row: LedgerRow) => string | null | undefined) =>
    rows.reduce((max, row) => {
      const value = selector(row);
      return Math.max(max, value ? value.length : 0);
    }, 0);

  const maxSupplierLength = maxLength((row) => row.person?.nome);
  const maxPropertyLength = maxLength((row) => row.property?.nome);

  const dateWidth = 6.5;
  const dueWidth = 6.5;
  const statusWidth = 6;
  const valueWidth = 8;

  const availableWidth = 100 - (dateWidth + dueWidth + statusWidth + valueWidth);
  const descriptionMinWidth = 24;
  const supplierMinWidth = 14;
  const supplierMaxWidth = 24;
  const propertyMinWidth = 14;
  const propertyMaxWidth = 34;

  const propertyTarget = propertyMinWidth + maxPropertyLength * 0.22;
  const propertyMaxAllowed = Math.max(propertyMinWidth, availableWidth - supplierMinWidth - descriptionMinWidth);
  let propertyWidth = Math.min(propertyMaxWidth, Math.max(propertyMinWidth, propertyTarget, propertyMinWidth));
  propertyWidth = Math.min(propertyWidth, propertyMaxAllowed);

  const supplierTarget = supplierMinWidth + maxSupplierLength * 0.18;
  let supplierWidth = Math.min(supplierMaxWidth, Math.max(supplierMinWidth, supplierTarget));
  const remainingAfterProperty = availableWidth - propertyWidth;
  const supplierMaxAllowed = Math.max(supplierMinWidth, remainingAfterProperty - descriptionMinWidth);

  if (supplierMaxAllowed <= supplierMinWidth) {
    supplierWidth = supplierMinWidth;
    propertyWidth = Math.max(
      propertyMinWidth,
      Math.min(propertyWidth, availableWidth - supplierWidth - descriptionMinWidth),
    );
  } else {
    supplierWidth = Math.min(supplierWidth, supplierMaxAllowed);
  }

  const descriptionWidth = Math.max(descriptionMinWidth, availableWidth - supplierWidth - propertyWidth);

  const toPercent = (width: number) => `${width}%`;

  return {
    date: toPercent(dateWidth),
    supplier: toPercent(supplierWidth),
    description: toPercent(descriptionWidth),
    property: toPercent(propertyWidth),
    due: toPercent(dueWidth),
    status: toPercent(statusWidth),
    value: toPercent(valueWidth),
  };
});

const isAllAccountsSelected = computed(() => !filters.financial_account_id);

const selectedAccount = computed(() => {
  if (isAllAccountsSelected.value) return null;
  const id = Number(filters.financial_account_id);
  return props.accounts.find((account) => account.id === id) ?? null;
});

const selectedAccountLabel = computed(() => {
  if (isAllAccountsSelected.value) {
    return 'Todos os bancos';
  }

  return selectedAccount.value?.nome ?? 'Conta não encontrada';
});

const formatCurrency = (value: number | string) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    typeof value === 'string' ? Number.parseFloat(value || '0') : value ?? 0,
  );

const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'open', label: 'Em aberto' },
  { value: 'settled', label: 'Quitado' },
  { value: 'overdue', label: 'Em atraso' },
  { value: 'cancelled', label: 'Cancelado' },
];

const typeOptions = [{ value: 'despesa', label: 'Despesas' }];

const persistFilters = () => {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      financial_account_id: String(filters.financial_account_id ?? ''),
      date_from: filters.date_from ?? '',
      date_to: filters.date_to ?? '',
      type: 'despesa',
      status: filters.status ?? '',
    }),
  );
};

const clearPersistedFilters = () => {
  if (typeof window === 'undefined') {
    return;
  }
  window.localStorage.removeItem(STORAGE_KEY);
};

const updateAccountSelectWidth = () => {
  if (typeof window === 'undefined') {
    accountSelectWidth.value = '100%';
    return;
  }

  const select = accountSelectRef.value;
  if (!select) {
    accountSelectWidth.value = '100%';
    return;
  }

  const computedStyle = window.getComputedStyle(select);
  const fontSize = computedStyle.fontSize || '14px';
  const fontFamily = computedStyle.fontFamily || 'Inter, ui-sans-serif, system-ui';

  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');

  if (!context) {
    accountSelectWidth.value = '100%';
    return;
  }

  context.font = `${fontSize} ${fontFamily}`;

  const optionLabels = [
    'Todos os bancos',
    ...props.accounts.map((account) => account.nome ?? ''),
  ];

  const textWidth = optionLabels.reduce((max, label) => {
    const measured = context.measureText(label).width;
    return measured > max ? measured : max;
  }, 0);

  const paddingLeft = Number.parseFloat(computedStyle.paddingLeft || '0');
  const paddingRight = Number.parseFloat(computedStyle.paddingRight || '0');
  const borderLeft = Number.parseFloat(computedStyle.borderLeftWidth || '0');
  const borderRight = Number.parseFloat(computedStyle.borderRightWidth || '0');

  const dropdownIndicatorAllowance = 32;

  const totalWidth = Math.ceil(
    textWidth + paddingLeft + paddingRight + borderLeft + borderRight + dropdownIndicatorAllowance,
  );

  accountSelectWidth.value = `min(100%, ${totalWidth}px)`;
};

const normalizeDate = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

const formatDate = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

const addDays = (date: Date, days: number) =>
  new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

const applyDatePreset = (preset: DatePreset) => {
  const today = normalizeDate(new Date());
  let start = today;
  let end = today;

  switch (preset) {
    case 'today':
      break;
    case 'yesterday':
      start = addDays(today, -1);
      end = addDays(today, -1);
      break;
    case 'tomorrow':
      start = addDays(today, 1);
      end = addDays(today, 1);
      break;
    case 'thisWeek': {
      const startOfWeek = addDays(today, -today.getDay());
      start = startOfWeek;
      end = addDays(startOfWeek, 6);
      break;
    }
    case 'lastWeek': {
      const startOfCurrentWeek = addDays(today, -today.getDay());
      start = addDays(startOfCurrentWeek, -7);
      end = addDays(start, 6);
      break;
    }
    case 'thisMonth': {
      start = new Date(today.getFullYear(), today.getMonth(), 1);
      end = today;
      break;
    }
    case 'thisYear': {
      start = new Date(today.getFullYear(), 0, 1);
      end = today;
      break;
    }
    default:
      break;
  }

  filters.date_from = formatDate(start);
  filters.date_to = formatDate(end);
  loadReport();
};

const loadReport = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const params: Record<string, any> = {};
    if (filters.financial_account_id) {
      params.financial_account_id = filters.financial_account_id;
    }
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    if (filters.type) params.type = filters.type;
    if (filters.status) params.status = filters.status;

    const { data } = await axios.get('/api/reports/bank-ledger', { params });

    ledgerRows.value = data.data ?? [];
    openingBalance.value = data.opening_balance ?? 0;
    closingBalance.value = data.closing_balance ?? 0;
    totals.value = data.totals ?? { inflow: 0, outflow: 0, net: 0 };
    reportAccountName.value = typeof data.account?.nome === 'string' && data.account.nome.trim() !== ''
      ? data.account.nome.trim()
      : selectedAccountLabel.value;

    persistFilters();
  } catch (error: any) {
    errorMessage.value =
      error?.response?.data?.message ?? 'Não foi possível carregar o extrato detalhado.';
    reportAccountName.value = selectedAccountLabel.value;
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.financial_account_id = '';
  filters.date_from = '';
  filters.date_to = '';
  filters.type = 'despesa';
  filters.status = '';

  clearPersistedFilters();
};

const exportReport = () => {
  if (!props.canExport) {
    return;
  }

  const params = new URLSearchParams();
  if (filters.financial_account_id) {
    params.append('financial_account_id', String(filters.financial_account_id));
  }
  if (filters.date_from) params.append('date_from', filters.date_from);
  if (filters.date_to) params.append('date_to', filters.date_to);
  params.append('type', 'despesa');
  if (filters.status) params.append('status', filters.status);
  params.append('format', 'csv');

  window.location.href = `/api/reports/bank-ledger/export?${params.toString()}`;
};

const exportPdf = () => {
  if (!props.canExport) {
    return;
  }

  const params = new URLSearchParams();
  if (filters.financial_account_id) {
    params.append('financial_account_id', String(filters.financial_account_id));
  }
  if (filters.date_from) params.append('date_from', filters.date_from);
  if (filters.date_to) params.append('date_to', filters.date_to);
  params.append('type', 'despesa');
  if (filters.status) params.append('status', filters.status);
  params.append('format', 'pdf');

  window.location.href = `/api/reports/bank-ledger/export?${params.toString()}`;
};

onMounted(() => {
  if (!filters.financial_account_id && props.accounts.length === 1 && !savedFilters.financial_account_id) {
    filters.financial_account_id = props.accounts[0].id;
  } else {
    loadReport();
  }

  nextTick(updateAccountSelectWidth);
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateAccountSelectWidth);
  }
});

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateAccountSelectWidth);
  }
});

watch(
  () => filters.financial_account_id,
  (value, oldValue) => {
    if (value === oldValue) {
      return;
    }

    reportAccountName.value = selectedAccountLabel.value;
    loadReport();
  },
);

watch(
  () => props.accounts,
  () => {
    nextTick(updateAccountSelectWidth);
  },
  { deep: true },
);
</script>

<template>
<AuthenticatedLayout title="Relatório de Despesas">
  <Head title="Relatório de Despesas" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="flex flex-col gap-1">
        <h1 class="text-xl font-semibold text-white">Relatório de Despesas</h1>
        <p class="text-sm text-slate-400">
          Gere um extrato consolidado a partir dos lançamentos financeiros, com saldo acumulado.
        </p>
      </header>

      <form class="space-y-4" @submit.prevent="loadReport">
        <div
          class="grid gap-4 md:grid-cols-2 xl:[grid-template-columns:auto_repeat(4,_minmax(0,_1fr))] xl:items-end"
        >
          <div class="flex flex-col gap-1 min-w-[12rem]">
            <label class="text-xs font-semibold text-slate-400">Conta bancária</label>
            <select
              ref="accountSelectRef"
              v-model="filters.financial_account_id"
              class="mt-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
              :style="{ width: accountSelectWidth }"
            >
              <option value="">Todos os bancos</option>
              <option v-for="account in props.accounts" :key="account.id" :value="account.id">
                {{ account.nome }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1 min-w-[12rem]">
            <label class="text-xs font-semibold text-slate-400">Período inicial</label>
            <DatePicker v-model="filters.date_from" placeholder="dd/mm/aaaa" />
          </div>
          <div class="flex flex-col gap-1 min-w-[12rem]">
            <label class="text-xs font-semibold text-slate-400">Período final</label>
            <DatePicker v-model="filters.date_to" placeholder="dd/mm/aaaa" />
          </div>
          <div class="flex flex-col gap-1 min-w-[12rem]">
            <label class="text-xs font-semibold text-slate-400">Tipo</label>
            <select
              v-model="filters.type"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1 min-w-[12rem]">
            <label class="text-xs font-semibold text-slate-400">Status</label>
            <select
              v-model="filters.status"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="preset in datePresets"
            :key="preset.id"
            type="button"
            class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs font-medium text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
            :disabled="loading"
            @click="applyDatePreset(preset.id)"
          >
            {{ preset.label }}
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
            :disabled="loading"
          >
            {{ loading ? 'Carregando...' : 'Gerar relatório' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
            :disabled="loading"
            @click="resetFilters"
          >
            Limpar filtros
          </button>
          <button
            v-if="props.canExport"
            type="button"
            class="rounded-lg border border-emerald-600 px-4 py-2 text-sm text-emerald-200 transition hover:bg-emerald-600/20 disabled:opacity-60"
            :disabled="loading"
            @click="exportReport"
          >
            Exportar CSV
          </button>
          <button
            v-if="props.canExport"
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-200/10 disabled:opacity-60"
            :disabled="loading"
            @click="exportPdf"
          >
            Exportar PDF
          </button>
        </div>
      </form>

      <p
        v-if="errorMessage"
        class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </p>

      <div v-if="!loading" class="grid gap-4 md:grid-cols-4">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Conta selecionada</p>
          <p class="text-lg font-semibold text-white">
            {{ reportAccountName }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saldo inicial</p>
          <p class="text-lg font-semibold text-slate-200">
            {{ formatCurrency(openingBalance) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Entradas</p>
          <p class="text-lg font-semibold text-emerald-300">
            {{ formatCurrency(totals.inflow) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saídas</p>
          <p class="text-lg font-semibold text-rose-300">
            {{ formatCurrency(totals.outflow) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm md:col-span-2">
          <p class="text-slate-400">Saldo final</p>
          <p
            :class="[
              'text-2xl font-semibold',
              closingBalance >= 0 ? 'text-emerald-300' : 'text-rose-300',
            ]"
          >
            {{ formatCurrency(closingBalance) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm md:col-span-2">
          <p class="text-slate-400">Resultado no período</p>
          <p
            :class="[
              'text-2xl font-semibold',
              totals.net >= 0 ? 'text-emerald-300' : 'text-rose-300',
            ]"
          >
            {{ formatCurrency(totals.net) }}
          </p>
        </article>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800">
        <table class="min-w-full table-fixed divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left" :style="{ width: columnWidths.date }">Data</th>
              <th class="px-4 py-3 text-left" :style="{ width: columnWidths.supplier }">Fornecedor</th>
              <th class="px-4 py-3 text-left" :style="{ width: columnWidths.description }">Descrição</th>
              <th class="px-4 py-3 text-left" :style="{ width: columnWidths.property }">Imóvel</th>
              <th class="px-4 py-3 text-left" :style="{ width: columnWidths.due }">Venc.</th>
              <th class="px-4 py-3 text-left" :style="{ width: columnWidths.status }">Status</th>
              <th class="px-4 py-3 text-right" :style="{ width: columnWidths.value }">Valor</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-8 text-center text-slate-400">Carregando dados...</td>
            </tr>
            <tr v-else-if="!ledgerRows.length">
              <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                Nenhum lançamento encontrado para os filtros selecionados.
              </td>
            </tr>
            <tr v-for="row in ledgerRows" :key="row.id">
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ row.movement_date ?? '-' }}
              </td>
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ row.person?.nome ?? '—' }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap overflow-hidden text-ellipsis">
                <span class="font-semibold text-white">{{ row.description ?? '-' }}</span>
              </td>
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                <span v-if="row.property?.nome">{{ row.property.nome }}</span>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ row.due_date ? new Date(row.due_date).toLocaleDateString('pt-BR') : '—' }}
              </td>
              <td class="px-4 py-3 text-xs text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ row.status_label ?? '—' }}
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap overflow-hidden text-ellipsis">
                {{ formatCurrency(row.signed_amount ?? 0) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AuthenticatedLayout>
</template>
