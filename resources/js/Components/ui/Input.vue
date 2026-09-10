<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
        type?: string;
        placeholder?: string;
        error?: string;
        hint?: string;
        id?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { type: 'text', disabled: false, required: false },
);

const model = defineModel<string | number>();
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-fg">
            {{ label }}
            <span v-if="required" class="text-danger-500" aria-hidden="true">*</span>
        </label>

        <input
            :id="id"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : undefined"
            :class="[
                'h-10 w-full rounded-lg border px-3 text-sm transition-colors duration-150',
                'bg-surface-raised text-fg placeholder:text-fg-subtle',
                'disabled:cursor-not-allowed disabled:opacity-50',
                error ? 'border-danger-500' : 'border-line hover:border-line-strong',
            ]"
        >

        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-xs text-danger-500">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-fg-subtle">{{ hint }}</p>
    </div>
</template>
