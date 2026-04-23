<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

class RichTextSanitizer {
	/** @var array<string, list<string>> */
	private const ALLOWED_ATTRIBUTES = [
		'a' => ['href', 'target', 'rel'],
		'img' => ['src', 'alt', 'width'],
		'p' => ['class'],
		'h2' => ['class'],
		'h3' => ['class'],
		'blockquote' => ['class'],
		'li' => ['data-list'],
	];

	/** @var list<string> */
	private const ALLOWED_TAGS = ['p', 'h2', 'h3', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'a', 'img'];

	/** @var list<string> */
	private const ALLOWED_CLASS_NAMES = ['ql-align-center', 'ql-align-right', 'ql-align-justify'];
    
	/** @var array<string, string> */
	private const ALIGNMENT_CLASS_BY_VALUE = [
		'center' => 'ql-align-center',
		'right' => 'ql-align-right',
		'justify' => 'ql-align-justify',
	];

	public function sanitize(?string $value): string {
		$html = trim((string) $value);
		if ($html === '') {
			return '';
		}

		$document = new \DOMDocument('1.0', 'UTF-8');
		$previousState = libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previousState);

		$container = $document->getElementsByTagName('div')->item(0);
		if (!$container instanceof \DOMElement) {
			return '';
		}

		$this->normalizeQuillMarkup($container);
		$this->sanitizeNode($container);
		$sanitized = trim($this->innerHtml($container));

		return $this->isMeaningful($sanitized) ? $sanitized : '';
	}

	public function isMeaningful(?string $value): bool {
		$sanitized = trim((string) $value);
		if ($sanitized === '') {
			return false;
		}

		return $this->toPlainText($sanitized) !== '' || str_contains(strtolower($sanitized), '<img');
	}

	public function toPlainText(?string $value): string {
		$sanitized = trim((string) $value);
		if ($sanitized === '') {
			return '';
		}

		return trim(preg_replace('/\s+/u', ' ', strip_tags($sanitized)) ?? '');
	}

	private function sanitizeNode(\DOMNode $node): void {
		$children = [];
		foreach ($node->childNodes as $child) {
			$children[] = $child;
		}

		foreach ($children as $child) {
			if (!$child instanceof \DOMElement) {
				continue;
			}

			$tag = strtolower($child->tagName);
			if (!in_array($tag, self::ALLOWED_TAGS, true)) {
				$this->unwrapNode($child);
				continue;
			}

			$this->normalizeTextAlignment($child);
			$this->sanitizeAttributes($child, $tag);
			$this->sanitizeNode($child);
		}
	}

	private function sanitizeAttributes(\DOMElement $element, string $tag): void {
		$allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
		$attributes = [];
		foreach ($element->attributes as $attribute) {
			$attributes[] = $attribute;
		}

		foreach ($attributes as $attribute) {
			$attributeName = strtolower($attribute->nodeName);
			if (!in_array($attributeName, $allowed, true)) {
				$element->removeAttribute($attribute->nodeName);
			}
		}

		if ($element->hasAttribute('class')) {
			$this->normalizeClassAttribute($element);
		}

		if ($tag === 'a') {
			$href = trim((string) $element->getAttribute('href'));
			if (!$this->isAllowedLink($href)) {
				$this->unwrapNode($element);
				return;
			}

			$element->setAttribute('target', '_blank');
			$element->setAttribute('rel', 'noopener noreferrer');
		}

		if ($tag === 'img') {
			$source = trim((string) $element->getAttribute('src'));
			if (!$this->isAllowedImageSource($source)) {
				$element->parentNode?->removeChild($element);
				return;
			}

			$this->normalizeImageWidth($element);
		}
	}

	private function normalizeImageWidth(
		\DOMElement $element,
	): void {
		if (!$element->hasAttribute('width')) {
			return;
		}

		$width = (int) $element->getAttribute('width');
		if ($width <= 0) {
			$element->removeAttribute('width');
			return;
		}

		$element->setAttribute('width', (string) $width);
	}

	private function normalizeClassAttribute(\DOMElement $element): void {
		$classNames = preg_split('/\s+/', trim((string) $element->getAttribute('class'))) ?: [];
		$filtered = array_values(array_filter($classNames, static fn (string $className): bool => in_array($className, self::ALLOWED_CLASS_NAMES, true)));
		if ($filtered === []) {
			$element->removeAttribute('class');
			return;
		}

		$element->setAttribute('class', implode(' ', $filtered));
	}

	private function normalizeTextAlignment(\DOMElement $element): void {
		$style = strtolower(trim((string) $element->getAttribute('style')));
		if ($style === '') {
			return;
		}

		if (!preg_match('/text-align\s*:\s*(center|right|justify)/', $style, $matches)) {
			$element->removeAttribute('style');
			return;
		}

		$alignment = $matches[1] ?? '';
		$alignmentClass = self::ALIGNMENT_CLASS_BY_VALUE[$alignment] ?? null;
		if ($alignmentClass === null) {
			$element->removeAttribute('style');
			return;
		}

		$classNames = preg_split('/\s+/', trim((string) $element->getAttribute('class'))) ?: [];
		$classNames[] = $alignmentClass;
		$element->setAttribute('class', implode(' ', array_values(array_unique(array_filter($classNames)))));
		$element->removeAttribute('style');
	}

	private function normalizeQuillMarkup(\DOMElement $root): void {
		$orderedLists = [];
		foreach ($root->getElementsByTagName('ol') as $orderedList) {
			$orderedLists[] = $orderedList;
		}

		foreach ($orderedLists as $orderedList) {
			if (!$orderedList instanceof \DOMElement) {
				continue;
			}

			$items = [];
			foreach ($orderedList->childNodes as $childNode) {
				if ($childNode instanceof \DOMElement && strtolower($childNode->tagName) === 'li') {
					$items[] = $childNode;
				}
			}

			if ($items === []) {
				continue;
			}

			$allBullets = true;
			foreach ($items as $item) {
				if ($item->getAttribute('data-list') !== 'bullet') {
					$allBullets = false;
					break;
				}
			}

			if (!$allBullets) {
				foreach ($items as $item) {
					$item->removeAttribute('data-list');
				}
				continue;
			}

			$unorderedList = $root->ownerDocument->createElement('ul');
			foreach ($items as $item) {
				$item->removeAttribute('data-list');
				$unorderedList->appendChild($item);
			}

			$orderedList->parentNode?->replaceChild($unorderedList, $orderedList);
		}
	}

	private function unwrapNode(\DOMElement $element): void {
		$parent = $element->parentNode;
		if ($parent === null) {
			return;
		}

		while ($element->firstChild !== null) {
			$parent->insertBefore($element->firstChild, $element);
		}

		$parent->removeChild($element);
	}

	private function innerHtml(\DOMElement $element): string {
		$html = '';
		foreach ($element->childNodes as $child) {
			$html .= $element->ownerDocument?->saveHTML($child) ?? '';
		}

		return $html;
	}

	private function isAllowedLink(string $href): bool {
		$normalized = strtolower(trim($href));
		return $normalized !== '' && (
			str_starts_with($normalized, 'https://')
			|| str_starts_with($normalized, 'http://')
			|| str_starts_with($normalized, 'mailto:')
		);
	}

	private function isAllowedImageSource(string $source): bool {
		$normalized = strtolower(trim($source));
		return $normalized !== '' && (
			str_starts_with($normalized, 'https://')
			|| str_starts_with($normalized, 'http://')
			|| preg_match('/^data:image\/(png|jpeg|jpg|gif|webp);base64,/', $normalized) === 1
		);
	}
}