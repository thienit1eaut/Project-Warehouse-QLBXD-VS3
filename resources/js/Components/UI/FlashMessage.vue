<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();

const success = computed(() => page.props.flash?.success);
const error   = computed(() => page.props.flash?.error);

// Tự ẩn sau 4 giây
const visible = ref(true);
watch([success, error], () => {
    visible.value = true;
    setTimeout(() => { visible.value = false; }, 4000);
}, { immediate: true });
</script>

<template>
    <transition
        enter-active-class="transition duration-300"
        enter-from-class="opacity-0 -translate-y-2"
        leave-active-class="transition duration-200"
        leave-to-class="opacity-0"
    >
        <div v-if="visible && (success || error)" class="mb-4">
            <div
                v-if="success"
                class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200
                       text-green-800 text-sm rounded-lg"
            >
                <span>✓</span> {{ success }}
            </div>
            <div
                v-if="error"
                class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200
                       text-red-800 text-sm rounded-lg"
            >
                <span>⚠</span> {{ error }}
            </div>
        </div>
    </transition>
</template>
