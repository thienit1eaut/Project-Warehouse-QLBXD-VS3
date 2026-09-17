<script setup>
defineProps({
    label:       { type: String,  default: '' },
    error:       { type: String,  default: '' },
    id:          { type: String,  default: '' },
    required:    { type: Boolean, default: false },
});
</script>

<template>
    <div class="space-y-1">
        <label
            v-if="label"
            :for="id"
            class="block text-sm font-medium text-gray-700"
        >
            {{ label }}
            <span v-if="required" class="text-red-500 ml-0.5">*</span>
        </label>

        <!-- Input được truyền vào qua slot mặc định -->
        <slot />

        <p v-if="error" class="text-xs text-red-600 flex items-center gap-1">
            <span>⚠</span> {{ error }}
        </p>
    </div>
</template>
