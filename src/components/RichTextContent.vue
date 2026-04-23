<script setup lang="ts">
import { computed } from 'vue'
import { sanitizeRichText } from '@/utils/richText'

const props = withDefaults(defineProps<{
	value?: string | null
	surface?: boolean
}>(), {
	value: '',
	surface: false,
})

const sanitizedValue = computed(() => sanitizeRichText(props.value ?? ''))
</script>

<template>
	<div class="gi-rich-text-content" :class="{ 'gi-rich-text-content--surface': surface }">
		<div class="gi-rich-text-content__scroll">
			<div class="gi-rich-text gi-rich-text-content__body" v-html="sanitizedValue" />
		</div>
	</div>
</template>

<style scoped>
.gi-rich-text-content {
	display: grid;
	min-width: 0;
	gap: 0;
}

.gi-rich-text-content--surface {
	padding: 1rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 16px;
	background: rgba(245, 249, 247, .96);
}

.gi-rich-text-content__scroll {
	min-width: 0;
	overflow-x: auto;
	overflow-y: hidden;
	-webkit-overflow-scrolling: touch;
	padding-bottom: .15rem;
}

.gi-rich-text-content__body {
	min-width: 0;
	text-align: left;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.gi-rich-text-content__body :deep(ul),
.gi-rich-text-content__body :deep(ol) {
	padding-left: 1.5rem;
	margin: 0 0 .8rem;
	margin-left: 0;
	list-style-position: outside !important;
}

.gi-rich-text-content__body :deep(ul) {
	list-style-type: disc !important;
}

.gi-rich-text-content__body :deep(ol) {
	list-style-type: decimal !important;
}

.gi-rich-text-content__body :deep(li) {
	display: list-item !important;
	margin: 0 0 .3rem;
	padding-left: 0;
}

.gi-rich-text-content__body :deep(ul > li::marker),
.gi-rich-text-content__body :deep(ol > li::marker) {
	color: currentColor;
	font-weight: 600;
}

.gi-rich-text-content__body :deep(li > p) {
	margin-bottom: .3rem;
}

.gi-rich-text-content__body :deep(li > p:last-child) {
	margin-bottom: 0;
}

@media (max-width: 900px) {
	.gi-rich-text-content__body {
		min-width: 100%;
	}

	.gi-rich-text-content__body :deep(img),
	.gi-rich-text-content__body img {
		max-width: none;
		width: auto;
	}
}
</style>