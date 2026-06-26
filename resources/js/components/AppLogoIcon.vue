<script setup lang="ts">
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <img
        src="/favicon.png"
        alt="NotionScheduler"
        class="object-contain"
        v-bind="$attrs"
    />
</template>
