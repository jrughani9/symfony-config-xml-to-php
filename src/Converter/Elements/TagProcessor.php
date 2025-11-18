<?php
/**
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

class TagProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('tag');
    }

    public function process(DOMElement $element): string
    {
        // Determine if tag name comes from attribute or text content
        $tagNameComesFromAttribute = $element->childElementCount || '' === $element->nodeValue;
        $tagName = $tagNameComesFromAttribute ? $element->getAttribute('name') : $element->nodeValue;

        if (!$tagName) {
            throw new \LogicException('The tag name must be a non-empty string.');
        }

        $output = $this->nl().'->tag(' . $this->formatString($tagName);

        // Check for attributes
        $attributes = [];
        foreach ($element->attributes as $attrName => $attrNode) {
            if ($tagNameComesFromAttribute && $attrName === 'name') {
                continue;
            }

            $attributes[$attrName] = $this->formatValue($attrNode->nodeValue);
        }

        // Check for nested attributes
        foreach ($element->childNodes as $childNode) {
            if (!($childNode instanceof DOMElement) || $childNode->nodeName !== 'attribute') {
                continue;
            }

            $attrName = $childNode->getAttribute('name');
            if ($childNode->childNodes->length > 0) {
                // Complex attribute - process as argument-like value
                $argumentProcessor = new ArgumentProcessor();
                $argumentProcessor->setIndentLevel($this->indentLevel);
                $attributes[$attrName] = $argumentProcessor->process($childNode);
            } else {
                $attributes[$attrName] = $this->formatValue($childNode->nodeValue);
            }
        }

        if (!empty($attributes)) {
            $outputs = [];
            foreach ($attributes as $key => $value) {
                if (str_contains($key, '-') && !str_contains($key, '_') && !\array_key_exists($normalizedName = str_replace('-', '_', $key), $attributes)) {
                    $key = $normalizedName;
                }

                $outputs[] = $this->formatString($key) . ' => ' . $value;
            }
            $output .= ', ['.implode(', ', $outputs).']';
        }

        return $output.')';
    }

    /**
     * Format a string value for PHP output (with quotes)
     */
    private function formatString(string $value): string
    {
        if (class_exists($value) || interface_exists($value) || trait_exists($value) || enum_exists($value)) {
            return '\\'.ltrim($value, '\\') . '::class';
        }

        if (str_ends_with($value, '\\')) {
            $value = addcslashes($value, '\'\\');
        } else {
            $value = addcslashes($value, '\'');
        }

        return "'" . $value . "'";
    }

    /**
     * Format a value for PHP output, detecting type
     */
    private function formatValue(string $value): string
    {
        // Try to detect the value type
        if (strtolower($value) === 'true') {
            return 'true';
        }

        if (strtolower($value) === 'false') {
            return 'false';
        }

        if (strtolower($value) === 'null') {
            return 'null';
        }

        if (is_numeric($value)) {
            return $value;
        }

        // Check if it's a parameter reference
        if (preg_match('/^%(.+)%$/', $value)) {
            return $this->formatString($value);
        }

        // Check if it's a service reference
        if (str_starts_with($value, '@')) {
            return "service('" . substr($value, 1) . "')";
        }

        // Regular string
        return $this->formatString($value);
    }
}
