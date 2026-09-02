<script setup>
import { readableTextColor } from '@/Utils/color';
import { iconComponent } from '@/Utils/classTypeIcons';
import { computed } from 'vue';

// Neutralna etykieta wizualna typu zajęć: zaokrąglony kwadracik w kolorze typu
// z kontrastową ikoną w środku (spec sekcja 18 / Prompt 15a).
const props = defineProps({
    color: { type: String, default: '#6B7280' },
    icon: { type: String, default: 'Dumbbell' },
    size: { type: String, default: 'md' }, // 'sm' | 'md'
});

const box = computed(() => (props.size === 'sm' ? 'h-4 w-4 rounded' : 'h-6 w-6 rounded-md'));
const glyph = computed(() => (props.size === 'sm' ? 12 : 15));
const component = computed(() => iconComponent(props.icon));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center"
        :class="box"
        :style="{ backgroundColor: color, color: readableTextColor(color) }"
    >
        <component :is="component" :size="glyph" :stroke-width="2.25" />
    </span>
</template>
