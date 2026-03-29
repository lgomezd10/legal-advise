import { defineComponent } from 'vue'

export const SearchableSelectStub = defineComponent({
	name: 'SearchableSelect',
	props: {
		modelValue: { type: [String, Number, Boolean, Object], default: null },
		options: { type: Array, default: () => [] },
		placeholder: { type: String, default: '' },
		searchPlaceholder: { type: String, default: '' },
		clearable: { type: Boolean, default: false },
		allowCreate: { type: Boolean, default: false },
		createLabel: { type: String, default: '' },
	},
	emits: ['update:modelValue', 'change'],
	template: `
		<div class="searchable-select-stub">
			<span v-if="placeholder">{{ placeholder }}</span>
			<span v-if="searchPlaceholder">{{ searchPlaceholder }}</span>
			<span v-if="allowCreate">{{ createLabel || 'Crear' }}</span>
			<ul>
				<li v-for="option in options" :key="String(option.value)">{{ option.label }}</li>
			</ul>
		</div>
	`,
})

export const RichTextEditorStub = defineComponent({
	name: 'RichTextEditor',
	props: {
		modelValue: { type: String, default: '' },
		placeholder: { type: String, default: '' },
	},
	emits: ['update:modelValue'],
	template: `<textarea class="rich-text-editor-stub" :placeholder="placeholder" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
})

export const RichTextContentStub = defineComponent({
	name: 'RichTextContent',
	props: {
		value: { type: String, default: '' },
	},
	template: `<div class="rich-text-content-stub">{{ value }}</div>`,
})

export const AttachmentPickerStub = defineComponent({
	name: 'AttachmentPicker',
	props: {
		modelValue: { type: Object, default: () => ({ files: [], links: [] }) },
		allowedExtensions: { type: Array, default: () => [] },
		maxFileSizeMb: { type: Number, default: 25 },
	},
	emits: ['update:modelValue'],
	template: `<div class="attachment-picker-stub">Adjuntar archivos</div>`,
})

export const TypeCascadeSelectorStub = defineComponent({
	name: 'TypeCascadeSelector',
	props: {
		modelValue: { type: Array, default: () => [] },
		types: { type: Array, default: () => [] },
	},
	emits: ['update:modelValue'],
	template: `<div class="type-cascade-selector-stub">Selector de tipos</div>`,
})

export const TicketFormStub = defineComponent({
	name: 'TicketForm',
	template: `<div class="ticket-form-stub">TicketForm</div>`,
})

export const TicketListStub = defineComponent({
	name: 'TicketList',
	template: `<div class="ticket-list-stub">TicketList</div>`,
})

export const SupportFilterBuilderStub = defineComponent({
	name: 'SupportFilterBuilder',
	template: `<div class="support-filter-builder-stub">SupportFilterBuilder</div>`,
})

export const SupportTicketTableStub = defineComponent({
	name: 'SupportTicketTable',
	template: `<div class="support-ticket-table-stub">SupportTicketTable</div>`,
})

export const TicketSidebarPanelStub = defineComponent({
	name: 'TicketSidebarPanel',
	props: {
		readOnly: { type: Boolean, default: false },
		showRepeat: { type: Boolean, default: false },
		showFullscreen: { type: Boolean, default: false },
		fullscreen: { type: Boolean, default: false },
		initialTab: { type: String, default: 'detail' },
		initialComposerVisible: { type: Boolean, default: true },
	},
	emits: ['repeat', 'fullscreen', 'save', 'comment', 'download', 'reopen', 'assign-to-me'],
	template: `
		<div class="ticket-sidebar-panel-stub">
			<span>TicketSidebarPanel</span>
			<span>readonly: {{ readOnly }}</span>
			<span>showRepeat: {{ showRepeat }}</span>
			<span>showFullscreen: {{ showFullscreen }}</span>
			<span>initialTab: {{ initialTab }}</span>
			<span>initialComposerVisible: {{ initialComposerVisible }}</span>
			<slot name="actions" />
		</div>
	`,
})

export const PersonalConfigViewStub = defineComponent({
	name: 'PersonalConfigView',
	template: `<div class="personal-config-view-stub">PersonalConfigView</div>`,
})

export const SupportSettingsPanelStub = defineComponent({
	name: 'SupportSettingsPanel',
	template: `<div class="support-settings-panel-stub">SupportSettingsPanel</div>`,
})

export const AdminConsoleViewStub = defineComponent({
	name: 'AdminConsoleView',
	template: `<div class="admin-console-view-stub">AdminConsoleView</div>`,
})

export const NotificationMatrixStub = defineComponent({
	name: 'NotificationMatrix',
	template: `<div class="notification-matrix-stub">NotificationMatrix</div>`,
})

export const FilterCatalogEditorStub = defineComponent({
	name: 'FilterCatalogEditor',
	props: {
		title: { type: String, default: '' },
		description: { type: String, default: '' },
		saveLabel: { type: String, default: '' },
	},
	template: `<div class="filter-catalog-editor-stub"><h2>{{ title }}</h2><p>{{ description }}</p><span>{{ saveLabel }}</span></div>`,
})

export const AdminTypeTreeEditorStub = defineComponent({
	name: 'AdminTypeTreeEditor',
	template: `<div class="admin-type-tree-editor-stub">AdminTypeTreeEditor</div>`,
})