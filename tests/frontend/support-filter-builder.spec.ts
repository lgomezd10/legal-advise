import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import SupportFilterBuilder from '@/components/SupportFilterBuilder.vue'

const SearchableSelectInteractiveStub = defineComponent({
	name: 'SearchableSelect',
	props: {
		modelValue: { type: [String, Number, Boolean, Object], default: null },
		options: { type: Array, default: () => [] },
	},
	emits: ['update:modelValue'],
	template: `
		<select class="searchable-select-interactive-stub" :value="modelValue ?? ''" @change="$emit('update:modelValue', $event.target.value || null)">
			<option value="">Seleccione</option>
			<option v-for="option in options" :key="String(option.value)" :value="String(option.value)">{{ option.label }}</option>
		</select>
	`,
})

describe('SupportFilterBuilder', () => {
	it('ajusta automaticamente rangos de fecha inconsistentes', async() => {
		const wrapper = mount(SupportFilterBuilder, {
			props: {
				filters: [],
				statuses: [],
				types: [],
				provinces: [],
				users: [],
				groups: [],
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectInteractiveStub,
				},
			},
		})

		await wrapper.get('button[aria-label="Añadir condicion"]').trigger('click')
		const selects = wrapper.findAll('select.searchable-select-interactive-stub')
		await selects[1].setValue('createdAt')

		const dateInputs = wrapper.findAll('input[type="date"]')
		await dateInputs[1].setValue('2026-03-10')
		await dateInputs[0].setValue('2026-03-15')
		expect((dateInputs[1].element as HTMLInputElement).value).toBe('2026-03-15')

		await dateInputs[1].setValue('2026-03-12')
		expect((dateInputs[0].element as HTMLInputElement).value).toBe('2026-03-12')

		await wrapper.get('button.gi-primary-button').trigger('click')
		const applyEvents = wrapper.emitted('apply') ?? []
		expect(applyEvents[applyEvents.length - 1]?.[0]).toEqual({ createdAtFrom: '2026-03-12', createdAtTo: '2026-03-12' })
	})

	it('desactiva estados inactivos y permite negar un criterio', async() => {
		const wrapper = mount(SupportFilterBuilder, {
			props: {
				filters: [],
				statuses: [
					{ id: 'nuevo', label: 'Nuevo', active: true },
					{ id: 'estado_1', label: 'Estado 1', active: false },
				],
				types: [],
				provinces: [],
				users: [],
				groups: [],
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectInteractiveStub,
				},
			},
		})

		await wrapper.get('button[aria-label="Añadir condicion"]').trigger('click')
		const selects = wrapper.findAll('select.searchable-select-interactive-stub')
		await selects[1].setValue('status')

		const checkboxes = wrapper.findAll('input[type="checkbox"]')
		expect((checkboxes[1].element as HTMLInputElement).disabled).toBe(true)

		await checkboxes[0].setValue(true)
		await checkboxes[2].setValue(true)
		await wrapper.get('button.gi-primary-button').trigger('click')

		const applyEvents = wrapper.emitted('apply') ?? []
		expect(applyEvents[applyEvents.length - 1]?.[0]).toEqual({ status: ['nuevo'], negatedCriteria: ['status'] })
	})
})