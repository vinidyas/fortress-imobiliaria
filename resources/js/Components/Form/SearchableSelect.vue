<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface BaseOption {
  value: number | null;
  label: string;
}

const props = withDefaults(
  defineProps<{
    modelValue: number | null;
    options: Array<{ value: number; label: string }>;
    disabled?: boolean;
    placeholder?: string;
    emptyLabel?: string;
    allowEmpty?: boolean;
    noResultsText?: string;
    openStrategy?: 'focus' | 'typing';
    showToggle?: boolean;
  }>(),
  {
    disabled: false,
    placeholder: '',
    emptyLabel: 'Nenhum',
    allowEmpty: true,
    noResultsText: 'Nenhum resultado encontrado.',
    openStrategy: 'focus',
    showToggle: true,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
  (e: 'search', term: string): void;
}>();

const rootRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const open = ref(false);
const searchTerm = ref('');
const highlightedIndex = ref(-1);

const normalizedOptions = computed<BaseOption[]>(() =>
  (props.options ?? []).map((option) => ({
    value: option.value,
    label: option.label ?? String(option.value),
  }))
);

const selectedOption = computed<BaseOption | null>(() => {
  if (props.modelValue === null || props.modelValue === undefined) {
    return null;
  }

  return normalizedOptions.value.find((option) => option.value === props.modelValue) ?? null;
});

const filteredOptions = computed<BaseOption[]>(() => {
  const term = searchTerm.value.trim().toLowerCase();
  let list = normalizedOptions.value;

  if (term !== '') {
    list = list.filter((option) => option.label.toLowerCase().includes(term));
  }

  if (props.allowEmpty) {
    return [
      { value: null, label: props.emptyLabel },
      ...list,
    ];
  }

  return list;
});

const hasResults = computed(() => filteredOptions.value.length > 0);

const updateSearchFromValue = () => {
  searchTerm.value = selectedOption.value?.label ?? '';
};

const inputClasses = computed(() =>
  [
    'w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-400',
    props.showToggle ? 'pr-16' : 'pr-12',
  ].join(' ')
);

const clearButtonPositionClass = computed(() => (props.showToggle ? 'right-9' : 'right-2'));

const closeDropdown = (options: { restoreSearch?: boolean } = {}) => {
  open.value = false;
  highlightedIndex.value = -1;
  if (options.restoreSearch !== false) {
    updateSearchFromValue();
  }
};

const focusInput = () => {
  nextTick(() => {
    if (inputRef.value) {
      inputRef.value.focus({ preventScroll: true });
      inputRef.value.select();
    }
  });
};

const updateHighlight = () => {
  if (!open.value) {
    return;
  }

  if (!filteredOptions.value.length) {
    highlightedIndex.value = -1;
    return;
  }

  const currentValue = props.modelValue;
  const currentIndex =
    currentValue === null
      ? filteredOptions.value.findIndex((option) => option.value === null)
      : filteredOptions.value.findIndex((option) => option.value === currentValue);

  highlightedIndex.value = currentIndex >= 0 ? currentIndex : 0;
};

const openDropdown = (source: 'focus' | 'toggle' | 'typing' = 'focus') => {
  if (props.disabled) {
    return;
  }

  if (props.openStrategy === 'typing' && source !== 'typing' && !searchTerm.value.trim()) {
    return;
  }

  open.value = true;
  if (source !== 'typing') {
    searchTerm.value = selectedOption.value?.label ?? '';
  }
  updateHighlight();
  if (source !== 'typing') {
    focusInput();
  }
};

const toggleDropdown = () => {
  if (open.value) {
    closeDropdown();
  } else {
    openDropdown('toggle');
  }
};

const handleInputFocus = () => {
  if (props.openStrategy === 'focus') {
    openDropdown('focus');
  }
};

const handleInputClick = () => {
  if (props.openStrategy === 'focus') {
    openDropdown('focus');
  }
};

const selectOption = (option: BaseOption | null) => {
  if (!option) {
    return;
  }

  if (option.value === null && !props.allowEmpty) {
    return;
  }

  emit('update:modelValue', option.value);
  searchTerm.value = option.label;
  closeDropdown();
};

const clearSelection = () => {
  if (!props.allowEmpty || props.disabled) {
    return;
  }
  emit('update:modelValue', null);
  searchTerm.value = '';
  closeDropdown({ restoreSearch: false });
};

const handleKeydown = (event: KeyboardEvent) => {
  if (!open.value && (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ')) {
    if (props.openStrategy === 'typing' && !searchTerm.value.trim()) {
      return;
    }
    event.preventDefault();
    openDropdown(props.openStrategy === 'typing' ? 'typing' : 'toggle');
    return;
  }

  if (!open.value) {
    return;
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (!filteredOptions.value.length) return;
    highlightedIndex.value =
      (highlightedIndex.value + 1 + filteredOptions.value.length) %
      filteredOptions.value.length;
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    if (!filteredOptions.value.length) return;
    highlightedIndex.value =
      (highlightedIndex.value - 1 + filteredOptions.value.length) %
      filteredOptions.value.length;
  } else if (event.key === 'Enter') {
    event.preventDefault();
    if (highlightedIndex.value >= 0) {
      selectOption(filteredOptions.value[highlightedIndex.value]);
    }
  } else if (event.key === 'Escape') {
    event.preventDefault();
    closeDropdown();
  }
};

const handleClickOutside = (event: MouseEvent) => {
  const root = rootRef.value;
  if (!root) return;
  if (root.contains(event.target as Node)) return;
  closeDropdown({ restoreSearch: props.openStrategy !== 'typing' });
};

watch(
  () => props.modelValue,
  () => {
    if (!open.value) {
      updateSearchFromValue();
    } else {
      updateHighlight();
    }
  },
  { immediate: true }
);

watch(
  () => props.options,
  () => {
    if (!open.value) {
      updateSearchFromValue();
    } else {
      updateHighlight();
    }
  },
  { deep: true }
);

watch(
  () => searchTerm.value,
  () => {
    emit('search', searchTerm.value);

    if (props.openStrategy === 'typing') {
      const hasTerm = searchTerm.value.trim().length > 0;
      if (hasTerm) {
        if (!open.value) {
          openDropdown('typing');
        } else {
          updateHighlight();
        }
      } else if (open.value) {
        closeDropdown({ restoreSearch: false });
      }
    } else if (open.value) {
      updateHighlight();
    }
  }
);

watch(
  () => filteredOptions.value,
  () => {
    if (open.value) {
      updateHighlight();
    }
  }
);

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
  <div ref="rootRef" class="relative">
    <input
      ref="inputRef"
      v-model="searchTerm"
      type="text"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="inputClasses"
      @focus="handleInputFocus"
      @click="handleInputClick"
      @keydown="handleKeydown"
    />

    <button
      v-if="allowEmpty && !disabled && modelValue !== null"
      type="button"
      :class="[
        'absolute inset-y-0 flex items-center px-2 text-slate-400 transition hover:text-slate-600',
        clearButtonPositionClass
      ]"
      @mousedown.prevent
      @click.prevent="clearSelection"
    >
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
      </svg>
    </button>

    <button
      v-if="showToggle"
      type="button"
      class="absolute inset-y-0 right-0 flex items-center px-2 text-slate-400 transition hover:text-slate-600"
      :class="{ 'cursor-default': disabled }"
      @mousedown.prevent
      @click.prevent="toggleDropdown"
    >
      <svg
        class="h-4 w-4 transition-transform"
        :class="{ 'rotate-180': open }"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
      </svg>
    </button>

    <transition name="fade">
      <div
        v-if="open"
        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg shadow-slate-900/10"
      >
        <ul class="divide-y divide-slate-100 text-sm">
          <li
            v-for="(option, index) in filteredOptions"
            :key="option.value ?? 'empty'"
            class="cursor-pointer px-3 py-2 transition"
            :class="[
              index === highlightedIndex
                ? 'bg-indigo-600 text-white'
                : 'text-slate-700 hover:bg-indigo-50',
              option.value === null && !allowEmpty ? 'text-slate-400' : '',
            ]"
            @mousedown.prevent="selectOption(option)"
            @mousemove="highlightedIndex = index"
          >
            {{ option.label }}
          </li>
          <li v-if="!hasResults" class="px-3 py-2 text-sm text-slate-500">
            {{ noResultsText }}
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.12s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
