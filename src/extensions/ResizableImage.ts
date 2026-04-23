import Image from '@tiptap/extension-image'
import { mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import RichTextImageNodeView from '@/components/RichTextImageNodeView.vue'

function normalizeWidth(value: unknown) {
	const numericValue = Number(value)
	if (!Number.isFinite(numericValue) || numericValue <= 0) {
		return null
	}

	return Math.round(numericValue)
}

const ResizableImage = Image.extend({
	addAttributes() {
		return {
			...this.parent?.(),
			width: {
				default: null,
				parseHTML: (element) => normalizeWidth(element.getAttribute('width')),
				renderHTML: (attributes) => {
					const width = normalizeWidth(attributes.width)
					return width ? { width: String(width) } : {}
				},
			},
		}
	},

	renderHTML({ HTMLAttributes }) {
		return ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)]
	},

	addNodeView() {
		return VueNodeViewRenderer(RichTextImageNodeView)
	},
})

export default ResizableImage