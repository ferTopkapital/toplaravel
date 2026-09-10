<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
        placeholder?: string;
        error?: string;
        hint?: string;
        id?: string;
        rows?: number;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { rows: 4, disabled: false, required: false },
);

const model = defineModel<string>();
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-fg">
            {{ label }}
            <span v-if="required" class="text-danger-500" aria-hidden="true">*</span>
        </label>

        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :aria-invalid="!!error"
            :class="[
                'w-full resize-y rounded-lg border px-3 py-2 text-sm transition-colors duration-150',
                'bg-surface-raised text-fg placeholder:text-fg-subtle',
                'disabled:cursor-not-allowed disabled:opacity-50',
                error ? 'border-danger-500' : 'border-line hover:border-line-strong',
            ]"
        />

        <p v-if="error" class="mt-1.5 text-xs text-danger-500">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-fg-subtle">{{ hint }}</p>
    </div>
</template>
