<script setup lang="ts">
import { computed, markRaw, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { isRichTextEmpty, richTextToPlainText, sanitizeRichText, sanitizeRichTextForPaste } from '@/utils/richText'

type RichTextEditorChain = {
	focus(): RichTextEditorChain
	toggleHeading(options: { level: 2 | 3 }): RichTextEditorChain
	toggleBold(): RichTextEditorChain
	toggleItalic(): RichTextEditorChain
	toggleUnderline(): RichTextEditorChain
	toggleOrderedList(): RichTextEditorChain
	toggleBulletList(): RichTextEditorChain
	toggleBlockquote(): RichTextEditorChain
	setTextAlign(alignment: 'left' | 'center' | 'right'): RichTextEditorChain
	unsetAllMarks(): RichTextEditorChain
	clearNodes(): RichTextEditorChain
	setImage(options: { src: string, alt: string, width?: number }): RichTextEditorChain
	setLink(options: { href: string }): RichTextEditorChain
	unsetLink(): RichTextEditorChain
	run(): boolean
}

type RichTextEditorInstance = {
	destroy(): void
	getHTML(): string
	setEditable(editable: boolean, emitUpdate?: boolean): void
	chain(): RichTextEditorChain
	state?: {
		selection?: {
			$from?: {
				parent?: {
					textContent?: string
				}
			}
		}
	}
	commands: {
		setContent(content: string, options?: { emitUpdate?: boolean }): void
	}
	isActive(name: string, attributes?: Record<string, unknown>): boolean
	getAttributes(name: string): Record<string, unknown>
}

type TiptapEditorConstructor = new (options: Record<string, unknown>) => RichTextEditorInstance

type TiptapModuleSet = {
	Editor: TiptapEditorConstructor
	EditorContent: unknown
	StarterKit: { configure(options: Record<string, unknown>): unknown }
	Link: { configure(options: Record<string, unknown>): unknown }
	Placeholder: { configure(options: Record<string, unknown>): unknown }
	TextAlign: { configure(options: Record<string, unknown>): unknown }
	Underline: unknown
	ResizableImage: { configure(options: Record<string, unknown>): unknown }
}

const props = withDefaults(defineProps<{
	modelValue?: string | null
	placeholder?: string
	disabled?: boolean
	minHeight?: number
}>(), {
	modelValue: '',
	placeholder: '',
	disabled: false,
	minHeight: 180,
})

const emit = defineEmits<{
	(e: 'update:modelValue', value: string): void
}>()

const editor = ref<RichTextEditorInstance | null>(null)
const editorContentComponent = shallowRef<unknown | null>(null)
const fallbackContent = ref('')
const showFallback = ref(true)
const fallbackReason = ref('')
const showHtmlSource = ref(false)
const htmlSource = ref('')
const mobileToolbarOpen = ref(false)
const toolbarShellRef = ref<HTMLElement | null>(null)
let syncingFromExternal = false
let pendingImageDialogFocusHandler: (() => void) | null = null
let isUnmounted = false
let pointerDownOutsideToolbarHandler: ((event: PointerEvent) => void) | null = null

async function loadTiptapModules(): Promise<TiptapModuleSet> {
	const [vueTiptap, starterKit, link, placeholder, textAlign, underline, resizableImage] = await Promise.all([
		import('@tiptap/vue-3'),
		import('@tiptap/starter-kit'),
		import('@tiptap/extension-link'),
		import('@tiptap/extension-placeholder'),
		import('@tiptap/extension-text-align'),
		import('@tiptap/extension-underline'),
		import('@/extensions/ResizableImage'),
	])

	return {
		Editor: vueTiptap.Editor as TiptapEditorConstructor,
		EditorContent: markRaw(vueTiptap.EditorContent),
		StarterKit: starterKit.default,
		Link: link.default,
		Placeholder: placeholder.default,
		TextAlign: textAlign.default,
		Underline: underline.default,
		ResizableImage: resizableImage.default,
	}
}

function normalizeEditorContent(value: string | null | undefined) {
	const sanitized = sanitizeRichText(value)
	return isRichTextEmpty(sanitized) ? '<p></p>' : sanitized
}

const editorStyle = computed(() => ({ '--gi-rich-text-min-height': `${props.minHeight}px` }))

watch(() => props.modelValue, (value) => {
	const nextValue = sanitizeRichText(value)
	fallbackContent.value = richTextToPlainText(nextValue)
	htmlSource.value = nextValue
	setEditorHtml(nextValue)
}, { immediate: true })

watch(() => props.disabled, (disabled) => {
	editor.value?.setEditable(!disabled, false)
})

onMounted(() => {
	pointerDownOutsideToolbarHandler = (event: PointerEvent) => {
		if (!mobileToolbarOpen.value) {
			return
		}

		const target = event.target
		if (!(target instanceof Node)) {
			return
		}

		if (toolbarShellRef.value?.contains(target)) {
			return
		}

		mobileToolbarOpen.value = false
	}

	window.addEventListener('pointerdown', pointerDownOutsideToolbarHandler)
	void initializeEditor()
})

onBeforeUnmount(() => {
	isUnmounted = true
	if (pointerDownOutsideToolbarHandler) {
		window.removeEventListener('pointerdown', pointerDownOutsideToolbarHandler)
		pointerDownOutsideToolbarHandler = null
	}
	clearPendingImageDialogFocusHandler()
	editor.value?.destroy()
	editor.value = null
})

function refreshViewportAfterDialog() {
	window.requestAnimationFrame(() => {
		window.dispatchEvent(new Event('resize'))
	})
}

function clearPendingImageDialogFocusHandler() {
	if (!pendingImageDialogFocusHandler) {
		return
	}

	window.removeEventListener('focus', pendingImageDialogFocusHandler)
	pendingImageDialogFocusHandler = null
}

function emitCurrentValue(value: string | null | undefined) {
	const sanitized = sanitizeRichText(value)
	const nextValue = isRichTextEmpty(sanitized) ? '' : sanitized
	fallbackContent.value = richTextToPlainText(nextValue)
	emit('update:modelValue', nextValue)
}

function escapeHtml(value: string) {
	return value
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
}

function plainTextToHtml(value: string) {
	const trimmed = value.trim()
	if (trimmed === '') {
		return ''
	}

	return value
		.split(/\r?\n\r?\n/)
		.map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\r?\n/g, '<br>')}</p>`)
		.join('')
}

function onFallbackInput(event: Event) {
	const nextValue = (event.target as HTMLTextAreaElement).value
	fallbackContent.value = nextValue
	emitCurrentValue(plainTextToHtml(nextValue))
}

function setEditorHtml(value: string) {
	const instance = editor.value
	if (!instance || syncingFromExternal) {
		return
	}

	const nextValue = normalizeEditorContent(value)
	const currentValue = sanitizeRichText(instance.getHTML())
	if (nextValue === normalizeEditorContent(currentValue)) {
		return
	}

	syncingFromExternal = true
	instance.commands.setContent(nextValue, { emitUpdate: false })
	syncingFromExternal = false
}

async function initializeEditor() {
	showFallback.value = true
	fallbackReason.value = 'Cargando editor avanzado...'
	editorContentComponent.value = null
	editor.value?.destroy()
	editor.value = null

	try {
		const tiptapModules = await loadTiptapModules()
		if (isUnmounted) {
			return
		}

		let instance: RichTextEditorInstance | null = null
		instance = new tiptapModules.Editor({
			content: normalizeEditorContent(props.modelValue),
			editable: !props.disabled,
			extensions: [
				tiptapModules.StarterKit.configure({
					heading: { levels: [2, 3] },
					link: false,
					underline: false,
				}),
				tiptapModules.Underline,
				tiptapModules.Link.configure({
					openOnClick: false,
					HTMLAttributes: {
						target: '_blank',
						rel: 'noopener noreferrer',
					},
				}),
				tiptapModules.ResizableImage.configure({
					allowBase64: true,
				}),
				tiptapModules.Placeholder.configure({
					placeholder: props.placeholder,
				}),
				tiptapModules.TextAlign.configure({
					types: ['heading', 'paragraph'],
				}),
			],
			editorProps: {
				transformPastedHTML: (html: string) => sanitizeRichTextForPaste(html),
				handlePaste: (_view: unknown, event: ClipboardEvent) => {
					const clipboard = event.clipboardData
					if (!clipboard || !instance) {
						return false
					}

					const imageFiles = Array.from(clipboard.files as FileList).filter((file: File) => file.type.startsWith('image/'))
					if (imageFiles.length === 0) {
						return false
					}

					for (const file of imageFiles) {
						insertImageFromFile(file, instance)
					}

					return true
				},
			},
			onUpdate: ({ editor: nextEditor }: { editor: RichTextEditorInstance }) => {
				if (syncingFromExternal) {
					return
				}

				emitCurrentValue(nextEditor.getHTML())
			},
		})

		editorContentComponent.value = tiptapModules.EditorContent
		editor.value = instance
		showFallback.value = false
		fallbackReason.value = ''
	}
	catch (error) {
		console.error('No se ha podido inicializar Tiptap', error)
		editor.value?.destroy()
		editor.value = null
		editorContentComponent.value = null
		showFallback.value = true
		fallbackReason.value = 'El editor avanzado no se ha cargado. Puedes escribir aqui igualmente.'
	}
}

function runEditorCommand(callback: (instance: RichTextEditorInstance) => void) {
	if (!editor.value || props.disabled) {
		return
	}

	callback(editor.value)
}

function toggleHeading(level: 2 | 3) {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleHeading({ level }).run()
	})
}

function toggleBold() {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleBold().run()
	})
}

function toggleItalic() {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleItalic().run()
	})
}

function toggleUnderline() {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleUnderline().run()
	})
}

function toggleOrderedList() {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleOrderedList().run()
	})
}

function toggleBulletList() {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleBulletList().run()
	})
}

function toggleBlockquote() {
	runEditorCommand((instance) => {
		instance.chain().focus().toggleBlockquote().run()
	})
}

function getCurrentBlockText(instance: RichTextEditorInstance | null) {
	return instance?.state?.selection?.$from?.parent?.textContent?.trim() ?? ''
}

function isHeadingToolActive(level: 2 | 3) {
	const instance = editor.value
	if (!instance || isRichTextEmpty(instance.getHTML())) {
		return false
	}

	if (!instance?.isActive('heading', { level })) {
		return false
	}

	return getCurrentBlockText(instance) !== ''
}

function isTextAlignToolActive(alignment: 'left' | 'center' | 'right') {
	if (!editor.value || isRichTextEmpty(editor.value.getHTML())) {
		return false
	}

	if (alignment === 'left') {
		return false
	}

	return editor.value?.isActive('paragraph', { textAlign: alignment }) ?? false
}

function isEditorActive(name: string, attributes?: Record<string, unknown>) {
	return editor.value?.isActive(name, attributes) ?? false
}

function toggleLink() {
	const instance = editor.value
	if (!instance) {
		return
	}

	if (instance.isActive('link')) {
		instance.chain().focus().unsetLink().run()
		return
	}

	const currentHref = String(instance.getAttributes('link').href ?? '')
	const nextHref = window.prompt('Introduce la URL del enlace', currentHref)
	if (!nextHref) {
		return
	}

	instance.chain().focus().setLink({ href: nextHref.trim() }).run()
}

function setAlignment(alignment: 'left' | 'center' | 'right') {
	runEditorCommand((instance) => {
		instance.chain().focus().setTextAlign(alignment).run()
	})
}

function clearFormatting() {
	runEditorCommand((instance) => {
		instance.chain().focus().unsetAllMarks().clearNodes().run()
	})
}

function toggleHtmlSource() {
	if (showHtmlSource.value) {
		showHtmlSource.value = false
		setEditorHtml(htmlSource.value)
		return
	}

	htmlSource.value = sanitizeRichText(editor.value?.getHTML() ?? props.modelValue)
	showHtmlSource.value = true
}

function closeMobileToolbarMenu() {
	mobileToolbarOpen.value = false
}

function runToolbarAction(action: () => void) {
	action()
	closeMobileToolbarMenu()
}

function toggleMobileToolbarMenu() {
	if (props.disabled) {
		return
	}

	mobileToolbarOpen.value = !mobileToolbarOpen.value
}

function onHtmlSourceInput(event: Event) {
	const nextValue = (event.target as HTMLTextAreaElement).value
	htmlSource.value = nextValue
	emitCurrentValue(nextValue)
}

function insertImage(source: string, altText: string, width: number | null = null, targetEditor: RichTextEditorInstance | null = editor.value) {
	targetEditor?.chain().focus().setImage({ src: source, alt: altText, width: width ?? undefined }).run()
}

function insertImageFromFile(file: File, targetEditor: RichTextEditorInstance | null = editor.value) {
	const reader = new FileReader()
	reader.onload = () => {
		if (typeof reader.result !== 'string') {
			return
		}

		const probeImage = new Image()
		probeImage.onload = () => {
			const naturalWidth = Number.isFinite(probeImage.naturalWidth) && probeImage.naturalWidth > 0 ? probeImage.naturalWidth : null
			insertImage(reader.result as string, file.name, naturalWidth, targetEditor)
		}
		probeImage.onerror = () => {
			insertImage(reader.result as string, file.name, null, targetEditor)
		}
		probeImage.src = reader.result
	}
	reader.readAsDataURL(file)
}

function openImageDialog() {
	if (props.disabled) {
		return
	}

	clearPendingImageDialogFocusHandler()
	pendingImageDialogFocusHandler = () => {
		clearPendingImageDialogFocusHandler()
		refreshViewportAfterDialog()
	}

	window.addEventListener('focus', pendingImageDialogFocusHandler)
	const input = document.createElement('input')
	input.type = 'file'
	input.accept = 'image/*'
	input.multiple = true
	input.tabIndex = -1
	input.setAttribute('aria-hidden', 'true')
	input.style.position = 'fixed'
	input.style.top = '-100vh'
	input.style.left = '-100vw'
	input.style.width = '1px'
	input.style.height = '1px'
	input.style.opacity = '0'
	input.style.pointerEvents = 'none'

	input.addEventListener('change', () => {
		const selectedFiles = Array.from(input.files ?? [])
		refreshViewportAfterDialog()
		for (const file of selectedFiles) {
			if (file.type.startsWith('image/')) {
				insertImageFromFile(file)
			}
		}
		input.remove()
	}, { once: true })

	document.body.appendChild(input)
	input.click()
}
</script>

<template>
	<div class="gi-rich-text-editor" :class="{ 'gi-rich-text-editor--disabled': disabled }" :style="editorStyle">
		<div v-if="showFallback" class="gi-rich-text-editor__fallback-shell">
			<textarea
				class="gi-rich-text-editor__fallback"
				:disabled="disabled"
				:placeholder="placeholder"
				:value="fallbackContent"
				@input="onFallbackInput"
			/>
			<p class="gi-rich-text-editor__fallback-hint">{{ fallbackReason }}</p>
		</div>
		<div v-else class="gi-rich-text-editor__surface">
			<div ref="toolbarShellRef" class="gi-rich-text-editor__toolbar-shell">
				<div class="gi-rich-text-editor__toolbar-mobile-head">
					<button type="button" class="gi-rich-text-editor__mobile-menu-button" :class="{ 'gi-rich-text-editor__mobile-menu-button--active': mobileToolbarOpen }" :disabled="disabled" aria-label="Opciones de formato" :aria-expanded="mobileToolbarOpen" @click="toggleMobileToolbarMenu">
						Formato
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5z" fill="currentColor" /></svg>
					</button>
				</div>
				<div class="gi-rich-text-editor__toolbar" :class="{ 'gi-rich-text-editor__toolbar--mobile-open': mobileToolbarOpen }">
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isHeadingToolActive(2) }" :disabled="disabled || showHtmlSource" title="Título grande" aria-label="Título grande" @click="runToolbarAction(() => toggleHeading(2))">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h3v5h10V6h3v12h-3v-5H7v5H4z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isHeadingToolActive(3) }" :disabled="disabled || showHtmlSource" title="Subtítulo" aria-label="Subtítulo" @click="runToolbarAction(() => toggleHeading(3))">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h3v4h10V7h3v10h-3v-4H7v4H4z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('bold') }" :disabled="disabled || showHtmlSource" title="Negrita" aria-label="Negrita" @click="runToolbarAction(toggleBold)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h6.5a4 4 0 0 1 2.7 6.96A4.5 4.5 0 0 1 14 19H8zm3 3v3h3.5a1.5 1.5 0 0 0 0-3zm0 6v3h3a1.5 1.5 0 0 0 0-3z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('italic') }" :disabled="disabled || showHtmlSource" title="Cursiva" aria-label="Cursiva" @click="runToolbarAction(toggleItalic)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5v2h2.2l-2.4 10H7v2h7v-2h-2.2l2.4-10H17V5z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('underline') }" :disabled="disabled || showHtmlSource" title="Subrayado" aria-label="Subrayado" @click="runToolbarAction(toggleUnderline)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5v6a5 5 0 0 0 10 0V5h-2v6a3 3 0 0 1-6 0V5zm-1 14h12v-2H6z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('orderedList') }" :disabled="disabled || showHtmlSource" title="Lista numerada" aria-label="Lista numerada" @click="runToolbarAction(toggleOrderedList)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h2V5H3v1h1zm0 6h1v1H3v1h3v-3H4zm-1 5h1.8L3 20v1h3v-1H4.8L6 18.5V17H3zm5-11h13V5H8zm0 12h13v-2H8zm0-5h13v-2H8z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('bulletList') }" :disabled="disabled || showHtmlSource" title="Lista con viñetas" aria-label="Lista con viñetas" @click="runToolbarAction(toggleBulletList)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6.5A1.5 1.5 0 1 0 5 9.5A1.5 1.5 0 1 0 5 6.5M8 8h13V6H8zm-3 5.5A1.5 1.5 0 1 0 5 16.5A1.5 1.5 0 1 0 5 13.5M8 15h13v-2H8zm-3 4A1.5 1.5 0 1 0 5 22A1.5 1.5 0 1 0 5 19m3 1h13v-2H8z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('blockquote') }" :disabled="disabled || showHtmlSource" title="Cita" aria-label="Cita" @click="runToolbarAction(toggleBlockquote)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17h4l2-4V7H7zm8 0h4l2-4V7h-6z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isTextAlignToolActive('left') }" :disabled="disabled || showHtmlSource" title="Alinear a la izquierda" aria-label="Alinear a la izquierda" @click="runToolbarAction(() => setAlignment('left'))">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16V4H4zm0 4h10V8H4zm0 4h16v-2H4zm0 4h10v-2H4z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isTextAlignToolActive('center') }" :disabled="disabled || showHtmlSource" title="Centrar" aria-label="Centrar" @click="runToolbarAction(() => setAlignment('center'))">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16V4H4zm3 4h10V8H7zm-3 4h16v-2H4zm3 4h10v-2H7z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isTextAlignToolActive('right') }" :disabled="disabled || showHtmlSource" title="Alinear a la derecha" aria-label="Alinear a la derecha" @click="runToolbarAction(() => setAlignment('right'))">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16V4H4zm6 4h10V8H10zm-6 4h16v-2H4zm6 4h10v-2H10z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :class="{ 'gi-rich-text-editor__tool--active': isEditorActive('link') }" :disabled="disabled || showHtmlSource" title="Enlace" aria-label="Enlace" @click="runToolbarAction(toggleLink)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.6 13.4a1 1 0 0 0 1.4 1.4l4.24-4.24a3 3 0 0 0-4.24-4.24l-1.88 1.88a1 1 0 1 0 1.42 1.42l1.87-1.88a1 1 0 1 1 1.41 1.42zm2.8-2.8a1 1 0 0 0-1.4-1.4l-4.24 4.24a3 3 0 1 0 4.24 4.24l1.88-1.88a1 1 0 1 0-1.42-1.42l-1.87 1.88a1 1 0 1 1-1.41-1.42z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :disabled="disabled || showHtmlSource" title="Insertar imagen" aria-label="Insertar imagen" @click="runToolbarAction(openImageDialog)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2m0 2v10h14V7zm2 8l2.5-3 2.2 2.6 3-4L19 15zm2-5a1.5 1.5 0 1 0 0-3a1.5 1.5 0 1 0 0 3" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool" :disabled="disabled || showHtmlSource" title="Limpiar formato" aria-label="Limpiar formato" @click="runToolbarAction(clearFormatting)">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5.4 4 1.2 1.2 1.9-1.2h7l1.7 3h-3.4l-3.8 3.8-4.2-4.2zm11.3 8.5 1.4 1.4-8.6 8.6H8.1v-1.4zm-8.4-1.3 1.4-1.4 3.1 3.1-1.4 1.4z" fill="currentColor" /></svg>
				</button>
					<button type="button" class="gi-rich-text-editor__tool gi-rich-text-editor__tool--text" :class="{ 'gi-rich-text-editor__tool--active': showHtmlSource }" :disabled="disabled" :title="showHtmlSource ? 'Volver al editor visual' : 'Editar HTML'" :aria-label="showHtmlSource ? 'Volver al editor visual' : 'Editar HTML'" @click="runToolbarAction(toggleHtmlSource)">
						HTML
					</button>
				</div>
			</div>
			<textarea v-if="showHtmlSource" class="gi-rich-text-editor__html-source" :disabled="disabled" :value="htmlSource" aria-label="HTML del contenido" spellcheck="false" @input="onHtmlSourceInput" />
			<component :is="editorContentComponent" v-else :editor="editor as never" class="gi-rich-text-editor__content" />
		</div>
	</div>
</template>

<style scoped>
.gi-rich-text-editor {
	position: relative;
	display: grid;
	gap: .75rem;
}

.gi-rich-text-editor__surface {
	display: grid;
	gap: 0;
	width: 100%;
	min-width: 0;
}

.gi-rich-text-editor__toolbar-shell {
	display: grid;
	gap: 0;
	position: relative;
}

.gi-rich-text-editor__toolbar-mobile-head {
	display: none;
}

.gi-rich-text-editor__mobile-menu-button {
	display: inline-flex;
	align-items: center;
	justify-content: space-between;
	gap: .5rem;
	width: 100%;
	padding: .7rem .9rem;
	border: 1px solid var(--gi-color-border-strong);
	border-radius: 14px 14px 0 0;
	background: var(--gi-color-surface-subtle);
	color: var(--gi-color-primary-soft-text);
	font: inherit;
	font-weight: 700;
	cursor: pointer;
}

.gi-rich-text-editor__mobile-menu-button svg {
	width: 1rem;
	height: 1rem;
	transition: transform .18s ease;
}

.gi-rich-text-editor__mobile-menu-button--active svg {
	transform: rotate(180deg);
}

.gi-rich-text-editor__toolbar {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: .3rem;
	padding: .35rem .45rem;
	border: 1px solid var(--gi-color-border-strong);
	border-radius: 14px 14px 0 0;
	background: var(--gi-color-surface-subtle);
	box-sizing: border-box;
}

.gi-rich-text-editor__tool {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 2.15rem;
	height: 2.15rem;
	padding: 0;
	border: 1px solid transparent;
	border-radius: 10px;
	background: transparent;
	color: var(--gi-color-primary-soft-text);
	cursor: pointer;
}

.gi-rich-text-editor__tool svg {
	width: 1.1rem;
	height: 1.1rem;
}

.gi-rich-text-editor__tool--text {
	padding: 0 .7rem;
	min-width: auto;
	font-size: .78rem;
	font-weight: 700;
	letter-spacing: .04em;
}

.gi-rich-text-editor__tool:hover {
	background: var(--gi-color-primary-soft);
}

.gi-rich-text-editor__tool--active {
	background: var(--gi-color-primary-soft-hover);
	border-color: var(--gi-color-primary);
	color: var(--gi-color-primary);
}

.gi-rich-text-editor__tool:disabled {
	opacity: .5;
	cursor: default;
}

.gi-rich-text-editor__content {
	width: 100%;
	min-width: 0;
	border: 1px solid var(--gi-color-border-strong);
	border-top: none;
	border-radius: 0 0 14px 14px;
	background: var(--gi-color-surface);
	box-sizing: border-box;
	overflow-x: auto;
}

.gi-rich-text-editor__content :deep(.tiptap) {
	width: 100%;
	max-width: 100%;
	min-height: var(--gi-rich-text-min-height);
	padding: .95rem 1rem;
	font: inherit;
	line-height: 1.5;
	color: var(--gi-color-text);
	outline: none;
	word-break: break-word;
	overflow-wrap: anywhere;
	box-sizing: border-box;
}

.gi-rich-text-editor__content :deep(.tiptap p),
.gi-rich-text-editor__content :deep(.tiptap h2),
.gi-rich-text-editor__content :deep(.tiptap h3),
.gi-rich-text-editor__content :deep(.tiptap ul),
.gi-rich-text-editor__content :deep(.tiptap ol),
.gi-rich-text-editor__content :deep(.tiptap blockquote) {
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	margin: 0 0 .8rem;
}

.gi-rich-text-editor__content :deep(.tiptap p:last-child),
.gi-rich-text-editor__content :deep(.tiptap h2:last-child),
.gi-rich-text-editor__content :deep(.tiptap h3:last-child),
.gi-rich-text-editor__content :deep(.tiptap ul:last-child),
.gi-rich-text-editor__content :deep(.tiptap ol:last-child),
.gi-rich-text-editor__content :deep(.tiptap blockquote:last-child) {
	margin-bottom: 0;
}

.gi-rich-text-editor__content :deep(.tiptap h2) {
	font-size: 1.2rem;
	line-height: 1.2;
}

.gi-rich-text-editor__content :deep(.tiptap h3) {
	font-size: 1.05rem;
	line-height: 1.2;
}

.gi-rich-text-editor__content :deep(.tiptap ul),
.gi-rich-text-editor__content :deep(.tiptap ol) {
	padding-left: 1.5rem;
	margin-left: 0;
	list-style-position: outside !important;
}

.gi-rich-text-editor__content :deep(.tiptap ul) {
	list-style-type: disc !important;
}

.gi-rich-text-editor__content :deep(.tiptap ol) {
	list-style-type: decimal !important;
}

.gi-rich-text-editor__content :deep(.tiptap li) {
	display: list-item;
	margin: 0 0 .3rem;
}

.gi-rich-text-editor__content :deep(.tiptap ul > li::marker) {
	content: '• ';
	color: var(--gi-color-primary-soft-text);
}

.gi-rich-text-editor__content :deep(.tiptap ol > li::marker) {
	color: var(--gi-color-primary-soft-text);
}

.gi-rich-text-editor__content :deep(.tiptap blockquote) {
	padding-left: .9rem;
	border-left: 3px solid var(--gi-color-primary);
	color: var(--gi-color-text-muted);
	font-style: italic;
}

.gi-rich-text-editor__content :deep(.tiptap img) {
	display: block;
	max-width: 100%;
	height: auto;
	margin-top: .65rem;
	border-radius: 12px;
	box-shadow: 0 12px 26px var(--gi-color-shadow-medium);
}

.gi-rich-text-editor__content :deep(.gi-rich-text-image-node),
.gi-rich-text-editor__content :deep(.gi-rich-text-image-node__image) {
	max-width: none;
}

.gi-rich-text-editor__content :deep(.tiptap a) {
	color: var(--gi-color-primary);
	text-decoration: underline;
	text-decoration-thickness: .08em;
	text-underline-offset: .14em;
	font-weight: 600;
}

.gi-rich-text-editor__content :deep(.tiptap a:hover) {
	color: var(--gi-color-primary-hover);
}

.gi-rich-text-editor__content :deep(.tiptap .is-editor-empty:first-child::before) {
	content: attr(data-placeholder);
	float: left;
	color: var(--gi-color-text-muted);
	pointer-events: none;
	height: 0;
}

.gi-rich-text-editor__content :deep(.tiptap.ProseMirror-focused) {
	outline: 2px solid var(--gi-color-primary-soft);
	outline-offset: -1px;
}

.gi-rich-text-editor__content :deep(.tiptap[contenteditable='false']) {
	background: var(--gi-color-surface-subtle);
	color: var(--gi-color-text-muted);
	cursor: default;
}

.gi-rich-text-editor__fallback-shell {
	display: grid;
	gap: .5rem;
}

.gi-rich-text-editor__fallback {
	width: 100%;
	min-height: var(--gi-rich-text-min-height);
	padding: .95rem 1rem;
	border: 1px solid var(--gi-color-border-strong);
	border-radius: 14px;
	background: var(--gi-color-surface);
	font: inherit;
	line-height: 1.5;
	resize: vertical;
	box-sizing: border-box;
}

.gi-rich-text-editor__fallback-hint {
	margin: 0;
	font-size: .9rem;
	color: var(--gi-color-text-muted);
}

.gi-rich-text-editor__html-source {
	width: 100%;
	min-height: var(--gi-rich-text-min-height);
	padding: .9rem 1rem;
	border: 1px solid var(--gi-color-border-strong);
	border-top: none;
	border-radius: 0 0 14px 14px;
	background: var(--gi-color-surface);
	color: var(--gi-color-primary-soft-text);
	font: 500 .84rem/1.45 Consolas, 'Courier New', monospace;
	resize: vertical;
	box-sizing: border-box;
	outline: none;
	white-space: pre-wrap;
	word-break: break-word;
}

@media (max-width: 900px) {
	.gi-rich-text-editor__toolbar-mobile-head {
		display: block;
	}

	.gi-rich-text-editor__toolbar {
		display: none;
		position: absolute;
		top: calc(100% - 1px);
		left: 0;
		right: 0;
		z-index: 6;
		padding: .55rem;
		border-radius: 0 0 14px 14px;
		box-shadow: 0 18px 32px var(--gi-color-shadow-medium);
	}

	.gi-rich-text-editor__toolbar--mobile-open {
		display: flex;
	}

	.gi-rich-text-editor__content,
	.gi-rich-text-editor__html-source {
		border-top: 1px solid var(--gi-color-border-strong);
		border-radius: 0 0 14px 14px;
	}

	.gi-rich-text-editor__toolbar--mobile-open + .gi-rich-text-editor__html-source,
	.gi-rich-text-editor__toolbar--mobile-open + .gi-rich-text-editor__content {
		margin-top: 11.5rem;
	}

	.gi-rich-text-editor__tool--text {
		padding-inline: .55rem;
	}
}


.gi-rich-text-editor--disabled .gi-rich-text-editor__toolbar {
	opacity: .7;
	pointer-events: none;
}
</style>
