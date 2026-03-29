<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { NodeViewWrapper, type NodeViewProps } from '@tiptap/vue-3'

type ResizeCorner = 'nw' | 'ne' | 'sw' | 'se'

const MIN_WIDTH = 96
const MAX_WIDTH = 2400

const props = defineProps<NodeViewProps>()

const imageRef = ref<HTMLImageElement | null>(null)
const resizing = ref(false)
let removeResizeListeners: (() => void) | null = null

const showHandles = computed(() => props.selected || resizing.value)
const currentWidth = computed(() => {
	const numericWidth = Number(props.node.attrs.width)
	if (!Number.isFinite(numericWidth) || numericWidth <= 0) {
		return null
	}

	return Math.round(numericWidth)
})
const wrapperStyle = computed(() => currentWidth.value ? { width: `${currentWidth.value}px` } : {})
const imageStyle = computed(() => currentWidth.value ? { width: '100%' } : {})

function clearResizeListeners() {
	removeResizeListeners?.()
	removeResizeListeners = null
}

function clampWidth(value: number) {
	return Math.max(MIN_WIDTH, Math.min(MAX_WIDTH, Math.round(value)))
}

function startResize(corner: ResizeCorner, event: PointerEvent) {
	const image = imageRef.value
	if (!image) {
		return
	}

	event.preventDefault()
	event.stopPropagation()
	clearResizeListeners()

	const startX = event.clientX
	const startY = event.clientY
	const rect = image.getBoundingClientRect()
	const initialWidth = rect.width
	const naturalWidth = image.naturalWidth || rect.width || MIN_WIDTH
	const naturalHeight = image.naturalHeight || rect.height || MIN_WIDTH
	const aspectRatio = naturalHeight > 0 ? naturalWidth / naturalHeight : 1
	const horizontalDirection = corner.includes('w') ? -1 : 1
	const verticalDirection = corner.includes('n') ? -1 : 1
	resizing.value = true

	const handlePointerMove = (moveEvent: PointerEvent) => {
		const deltaX = (moveEvent.clientX - startX) * horizontalDirection
		const deltaY = (moveEvent.clientY - startY) * verticalDirection
		const widthFromVerticalMovement = deltaY * aspectRatio
		const dominantDelta = Math.abs(widthFromVerticalMovement) > Math.abs(deltaX) ? widthFromVerticalMovement : deltaX
		props.updateAttributes({ width: clampWidth(initialWidth + dominantDelta) })
	}

	const stopResize = () => {
		resizing.value = false
		clearResizeListeners()
	}

	window.addEventListener('pointermove', handlePointerMove)
	window.addEventListener('pointerup', stopResize)
	window.addEventListener('pointercancel', stopResize)
	removeResizeListeners = () => {
		window.removeEventListener('pointermove', handlePointerMove)
		window.removeEventListener('pointerup', stopResize)
		window.removeEventListener('pointercancel', stopResize)
	}
}

onBeforeUnmount(() => {
	clearResizeListeners()
})
</script>

<template>
	<NodeViewWrapper class="gi-rich-text-image-node" :class="{ 'gi-rich-text-image-node--selected': showHandles }" :style="wrapperStyle" contenteditable="false">
		<img
			ref="imageRef"
			class="gi-rich-text-image-node__image"
			:src="node.attrs.src"
			:alt="node.attrs.alt || ''"
			:title="node.attrs.title || ''"
			:width="currentWidth || undefined"
			:style="imageStyle"
			draggable="false"
		>
		<button v-if="showHandles" class="gi-rich-text-image-node__handle gi-rich-text-image-node__handle--nw" type="button" aria-label="Redimensionar imagen" @pointerdown="startResize('nw', $event)" />
		<button v-if="showHandles" class="gi-rich-text-image-node__handle gi-rich-text-image-node__handle--ne" type="button" aria-label="Redimensionar imagen" @pointerdown="startResize('ne', $event)" />
		<button v-if="showHandles" class="gi-rich-text-image-node__handle gi-rich-text-image-node__handle--sw" type="button" aria-label="Redimensionar imagen" @pointerdown="startResize('sw', $event)" />
		<button v-if="showHandles" class="gi-rich-text-image-node__handle gi-rich-text-image-node__handle--se" type="button" aria-label="Redimensionar imagen" @pointerdown="startResize('se', $event)" />
	</NodeViewWrapper>
</template>

<style scoped>
.gi-rich-text-image-node {
	position: relative;
	display: inline-flex;
	min-width: 0;
	line-height: 0;
	margin-top: .65rem;
	border-radius: 12px;
	user-select: none;
	touch-action: none;
	transition: box-shadow .18s ease, outline-color .18s ease;
}

.gi-rich-text-image-node__image {
	display: block;
	max-width: none;
	height: auto;
	border-radius: 12px;
	box-shadow: 0 12px 26px rgba(34, 62, 55, .12);
	user-select: none;
	-webkit-user-drag: none;
}

.gi-rich-text-image-node--selected {
	outline: 2px solid rgba(11, 110, 79, .28);
	outline-offset: 2px;
	box-shadow: 0 0 0 6px rgba(11, 110, 79, .08);
}

.gi-rich-text-image-node__handle {
	position: absolute;
	width: 1rem;
	height: 1rem;
	padding: 0;
	border: 2px solid #fff;
	border-radius: 999px;
	background: linear-gradient(180deg, #14956d 0%, #0b6e4f 100%);
	box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
	z-index: 2;
	transition: transform .14s ease, box-shadow .14s ease;
}

.gi-rich-text-image-node__handle:hover,
.gi-rich-text-image-node__handle:focus-visible {
	transform: scale(1.08);
	box-shadow: 0 8px 20px rgba(0, 0, 0, .22);
	outline: none;
}

.gi-rich-text-image-node__handle--nw {
	top: -.45rem;
	left: -.45rem;
	cursor: nwse-resize;
}

.gi-rich-text-image-node__handle--ne {
	top: -.45rem;
	right: -.45rem;
	cursor: nesw-resize;
}

.gi-rich-text-image-node__handle--sw {
	bottom: -.45rem;
	left: -.45rem;
	cursor: nesw-resize;
}

.gi-rich-text-image-node__handle--se {
	bottom: -.45rem;
	right: -.45rem;
	cursor: nwse-resize;
}

@media (pointer: coarse) {
	.gi-rich-text-image-node__handle {
		width: 1.15rem;
		height: 1.15rem;
	}
}
</style>