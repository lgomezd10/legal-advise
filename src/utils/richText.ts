import DOMPurify from 'dompurify'

type DomPurifyLike = {
	sanitize?: (value: string, options?: Record<string, unknown>) => string
	default?: {
		sanitize?: (value: string, options?: Record<string, unknown>) => string
	}
}

const PERSISTED_ALLOWED_TAGS = new Set(['p', 'h2', 'h3', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'a', 'img'])
const PASTE_ALLOWED_TAGS = ['p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'a', 'img']
const ALLOWED_ATTRS: Record<string, string[]> = {
	a: ['href', 'target', 'rel'],
	img: ['src', 'alt', 'width'],
	p: ['class'],
	h2: ['class'],
	h3: ['class'],
	blockquote: ['class'],
	li: ['data-list'],
}
const ALLOWED_CLASS_NAMES = new Set(['ql-align-center', 'ql-align-right', 'ql-align-justify'])
const FORBIDDEN_EMBED_TAGS = ['iframe', 'script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math']
const ALIGNMENT_CLASS_BY_VALUE: Record<string, string> = {
	center: 'ql-align-center',
	right: 'ql-align-right',
	justify: 'ql-align-justify',
}

function createDocumentFragment(html: string) {
	const parser = new DOMParser()
	return parser.parseFromString(`<div>${html}</div>`, 'text/html')
}

function normalizeText(text: string) {
	return text.replace(/\s+/g, ' ').trim()
}

function resolvePurifySanitize() {
	const candidate = DOMPurify as DomPurifyLike
	if (typeof candidate.sanitize === 'function') {
		return candidate.sanitize.bind(candidate)
	}

	if (typeof candidate.default?.sanitize === 'function') {
		return candidate.default.sanitize.bind(candidate.default)
	}

	return null
}

function purifyHtml(value: string, options: { allowedTags: string[], allowedAttrs: string[] }) {
	const sanitize = resolvePurifySanitize()
	if (!sanitize) {
		return value
	}

	try {
		return sanitize(value, {
		USE_PROFILES: { html: true },
		ALLOWED_TAGS: options.allowedTags,
		ALLOWED_ATTR: options.allowedAttrs,
		FORBID_TAGS: FORBIDDEN_EMBED_TAGS,
		KEEP_CONTENT: true,
		RETURN_TRUSTED_TYPE: false,
		})
	} catch {
		return value
	}
}

function isAllowedLink(href: string) {
	const normalized = href.trim().toLowerCase()
	return normalized.startsWith('https://') || normalized.startsWith('http://') || normalized.startsWith('mailto:')
}

function isAllowedImageSource(source: string) {
	const normalized = source.trim().toLowerCase()
	return normalized.startsWith('https://')
		|| normalized.startsWith('http://')
		|| /^data:image\/(png|jpeg|jpg|gif|webp);base64,/.test(normalized)
}

function unwrapElement(element: Element) {
	const parent = element.parentNode
	if (!parent) {
		element.remove()
		return
	}

	while (element.firstChild) {
		parent.insertBefore(element.firstChild, element)
	}

	parent.removeChild(element)
}

function normalizeQuillMarkup(root: Element) {
	for (const list of Array.from(root.querySelectorAll('ol'))) {
		const items = Array.from(list.children).filter((child): child is HTMLLIElement => child instanceof HTMLLIElement)
		if (items.length === 0) {
			continue
		}

		if (items.every((item) => item.getAttribute('data-list') === 'bullet')) {
			const unorderedList = root.ownerDocument.createElement('ul')
			for (const item of items) {
				item.removeAttribute('data-list')
				unorderedList.appendChild(item)
			}
			list.replaceWith(unorderedList)
			continue
		}

		for (const item of items) {
			item.removeAttribute('data-list')
		}
	}
}

function normalizeClassAttribute(element: HTMLElement) {
	const classNames = (element.getAttribute('class') ?? '').split(/\s+/).map((value) => value.trim()).filter(Boolean)
	const filtered = classNames.filter((value) => ALLOWED_CLASS_NAMES.has(value))
	if (filtered.length === 0) {
		element.removeAttribute('class')
		return
	}

	element.setAttribute('class', filtered.join(' '))
}

function normalizeTextAlignment(element: HTMLElement) {
	const styleValue = (element.getAttribute('style') ?? '').toLowerCase()
	const matchedAlignment = styleValue.match(/text-align\s*:\s*(center|right|justify)/)?.[1]
	if (!matchedAlignment) {
		return
	}

	const classNames = new Set((element.getAttribute('class') ?? '').split(/\s+/).map((value) => value.trim()).filter(Boolean))
	const alignmentClass = ALIGNMENT_CLASS_BY_VALUE[matchedAlignment]
	if (alignmentClass) {
		classNames.add(alignmentClass)
	}

	if (classNames.size > 0) {
		element.setAttribute('class', Array.from(classNames).join(' '))
	}

	element.removeAttribute('style')
}

function stripUnsafeLinksAndImages(root: Element) {
	for (const link of Array.from(root.querySelectorAll('a'))) {
		const href = link.getAttribute('href') ?? ''
		if (!isAllowedLink(href)) {
			unwrapElement(link)
			continue
		}

		link.setAttribute('target', '_blank')
		link.setAttribute('rel', 'noopener noreferrer')
	}

	for (const image of Array.from(root.querySelectorAll('img'))) {
		const source = image.getAttribute('src') ?? ''
		if (!isAllowedImageSource(source)) {
			image.remove()
			continue
		}

		normalizeImageWidthAttribute(image)
	}
}

function normalizeImageWidthAttribute(image: Element) {
	const widthValue = image.getAttribute('width')
	if (!widthValue) {
		return
	}

	const normalizedWidth = Number(widthValue)
	if (!Number.isFinite(normalizedWidth) || normalizedWidth <= 0) {
		image.removeAttribute('width')
		return
	}

	image.setAttribute('width', String(Math.round(normalizedWidth)))
}

function sanitizeNode(node: Node) {
	for (const child of Array.from(node.childNodes)) {
		if (child.nodeType !== Node.ELEMENT_NODE) {
			continue
		}

		const element = child as HTMLElement
		const tagName = element.tagName.toLowerCase()

		if (!PERSISTED_ALLOWED_TAGS.has(tagName)) {
			unwrapElement(element)
			sanitizeNode(node)
			continue
		}

		normalizeTextAlignment(element)

		for (const attribute of Array.from(element.attributes)) {
			const attrName = attribute.name.toLowerCase()
			const allowedAttrs = ALLOWED_ATTRS[tagName] ?? []
			if (!allowedAttrs.includes(attrName)) {
				element.removeAttribute(attribute.name)
			}
		}

		if (element.hasAttribute('class')) {
			normalizeClassAttribute(element)
		}

		if (tagName === 'a') {
			const href = element.getAttribute('href') ?? ''
			if (!isAllowedLink(href)) {
				unwrapElement(element)
				sanitizeNode(node)
				continue
			}
			element.setAttribute('target', '_blank')
			element.setAttribute('rel', 'noopener noreferrer')
		}

		if (tagName === 'img') {
			const source = element.getAttribute('src') ?? ''
			if (!isAllowedImageSource(source)) {
				element.remove()
				continue
			}
		}

		sanitizeNode(element)
	}
}

export function sanitizeRichTextForPaste(value: string | null | undefined) {
	const html = String(value ?? '')
	if (html.trim() === '') {
		return ''
	}

	const purified = purifyHtml(html, {
		allowedTags: PASTE_ALLOWED_TAGS,
		allowedAttrs: ['href', 'target', 'rel', 'src', 'alt', 'title', 'class', 'style', 'width'],
	})
	const document = createDocumentFragment(purified)
	const root = document.body.firstElementChild
	if (!root) {
		return ''
	}

	stripUnsafeLinksAndImages(root)
	return root.innerHTML.trim()
}

export function sanitizeRichText(value: string | null | undefined) {
	const html = String(value ?? '')
	if (html.trim() === '') {
		return ''
	}

	const purified = purifyHtml(html, {
		allowedTags: Array.from(PERSISTED_ALLOWED_TAGS),
		allowedAttrs: ['href', 'target', 'rel', 'src', 'alt', 'class', 'style', 'data-list', 'width'],
	})
	const document = createDocumentFragment(purified)
	const root = document.body.firstElementChild
	if (!root) {
		return ''
	}

	normalizeQuillMarkup(root)
	stripUnsafeLinksAndImages(root)
	sanitizeNode(root)
	const sanitized = root.innerHTML.trim()
	return isRichTextEmpty(sanitized) ? '' : sanitized
}

export function richTextToPlainText(value: string | null | undefined) {
	const sanitized = sanitizeRichText(value)
	if (sanitized === '') {
		return ''
	}

	const document = createDocumentFragment(sanitized)
	const root = document.body.firstElementChild
	if (!root) {
		return ''
	}

	const textContent = normalizeText(root.textContent ?? '')
	const imageCount = root.querySelectorAll('img').length
	if (imageCount === 0) {
		return textContent
	}

	return normalizeText(`${textContent} ${Array.from({ length: imageCount }, () => 'imagen').join(' ')}`)
}

export function isRichTextEmpty(value: string | null | undefined) {
	const sanitized = String(value ?? '').trim()
	if (sanitized === '') {
		return true
	}

	const document = createDocumentFragment(sanitized)
	const root = document.body.firstElementChild
	if (!root) {
		return true
	}

	return normalizeText(root.textContent ?? '') === '' && root.querySelector('img') === null
}

export function excerptRichText(value: string | null | undefined, maxLength = 140) {
	const plainText = richTextToPlainText(value)
	if (plainText.length <= maxLength) {
		return plainText
	}

	return `${plainText.slice(0, Math.max(0, maxLength - 1))}...`
}