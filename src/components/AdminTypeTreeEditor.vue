<script setup lang="ts">
import type { EditableTypeNode } from '@/types'

defineOptions({
	name: 'AdminTypeTreeEditor',
})

const props = withDefaults(defineProps<{
	nodes: EditableTypeNode[]
	pathPrefix?: string
}>(), {
	pathPrefix: '',
})

const emit = defineEmits<{
	(e: 'add-child', parent: EditableTypeNode): void
	(e: 'request-remove', clientId: string): void
}>()

function buildPath(node: EditableTypeNode): string {
	const name = node.name.trim() || 'Nuevo tipo'
	return props.pathPrefix ? `${props.pathPrefix} > ${name}` : name
}
</script>

<template>
	<ul class="gi-admin-tree">
		<li v-for="node in nodes" :key="node.clientId" class="gi-admin-tree__item">
			<div class="gi-admin-tree__row">
				<label class="gi-field gi-admin-tree__field">
					<span>Nombre</span>
					<input :id="`type-name-${node.clientId}`" v-model="node.name" :name="`type-name-${node.clientId}`" class="gi-input" type="text" placeholder="Nombre del tipo" />
				</label>
				<label class="gi-field gi-admin-tree__toggle">
					<span>Activo</span>
					<input :id="`type-active-${node.clientId}`" v-model="node.active" :name="`type-active-${node.clientId}`" type="checkbox" />
				</label>
				<div class="gi-admin-tree__actions">
					<span class="gi-meta-pill">{{ buildPath(node) }}</span>
					<button class="gi-round-icon-button gi-admin-tree__remove-button" type="button" :aria-label="`Eliminar ${buildPath(node)}`" :title="`Eliminar ${buildPath(node)}`" @click="emit('request-remove', node.clientId)">
						×
					</button>
					<button class="gi-secondary-button" type="button" @click="emit('add-child', node)">
						Añadir subtipo
					</button>
				</div>
			</div>
			<AdminTypeTreeEditor
				v-if="node.children.length > 0"
				:nodes="node.children"
				:path-prefix="buildPath(node)"
				@add-child="emit('add-child', $event)"
				@request-remove="emit('request-remove', $event)"
			/>
		</li>
	</ul>
</template>

<style scoped>
.gi-admin-tree {
	list-style: none;
	margin: 0;
	padding: 0;
	display: grid;
	gap: .8rem;
}

.gi-admin-tree__item {
	padding-left: .9rem;
	border-left: 2px solid rgba(11, 110, 79, .12);
	margin-left: .3rem;
}

.gi-admin-tree__row {
	display: grid;
	gap: .75rem;
	grid-template-columns: minmax(0, 2fr) auto minmax(0, 1.4fr);
	align-items: end;
	padding: .85rem;
	border-radius: 16px;
	background: rgba(236, 242, 239, .76);
	min-width: 0;
}

.gi-admin-tree__field,
.gi-admin-tree__toggle {
	margin: 0;
	min-width: 0;
}

.gi-admin-tree__toggle {
	min-width: 5rem;
	justify-items: center;
}

.gi-admin-tree__actions {
	display: flex;
	gap: .55rem;
	justify-content: flex-end;
	align-items: center;
	flex-wrap: wrap;
}

.gi-admin-tree__remove-button {
	width: 2rem;
	height: 2rem;
	color: var(--gi-color-danger, #b42318);
	font-size: 1.15rem;
	line-height: 1;
}

@media (max-width: 900px) {
	.gi-admin-tree__row {
		grid-template-columns: 1fr;
	}

	.gi-admin-tree__actions {
		justify-content: flex-start;
	}

	.gi-admin-tree__toggle {
		justify-items: flex-start;
	}
}
</style>