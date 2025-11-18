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

class ParameterProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('parameter');
    }

    public function process(DOMElement $element): string
    {
        return $this->formatParameter($element);
    }

    /**
     * Format a single parameter element
     */
    private function formatParameter(DOMElement $parameter): string
    {
        if ($parameter->tagName !== 'parameter') {
            throw new \LogicException('Expected a <parameter> element.');
        }

        $type = $parameter->getAttribute('type');
        $value = $parameter->nodeValue;

        // Handle nested parameters (collection)
        if ($type === 'collection') {
            $items = [];
            foreach ($parameter->childNodes as $item) {
                if (!$item instanceof DOMElement) {
                    continue;
                }

                $itemKey = $item->getAttribute('key');
                if ($itemKey) {
                    $items[] = $this->formatString($itemKey) . ' => ' . $this->formatParameter($item);
                } else {
                    $items[] = $this->formatParameter($item);
                }
            }

            return '[' . implode(', ', $items) . ']';
        }

        if (in_array($parameter->getAttribute('trim'), ['true', '1'], true)) {
            $value = trim($value);
        }

        return match ($type) {
            'string' => $this->formatString($value),
            'constant' => '\\'.ltrim($value, '\\'),
            'binary' => 'base64_decode('.$this->formatString($value).')',
            default => $this->formatValue($value),
        };
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

        // Check if it's a service reference (starts with @)
        // Keep it as a string literal, not a service() call for parameters
        if (str_starts_with($value, '@')) {
            return $this->formatString($value);
        }

        // Check if it's a class constant reference
        if (preg_match('/^[\w\\\\]+::[A-Z_]+$/', $value)) {
            return '\\'.ltrim($value, '\\');
        }

        // Regular string
        return $this->formatString($value);
    }
}
