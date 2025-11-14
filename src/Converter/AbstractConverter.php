<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter;

abstract class AbstractConverter implements ConverterInterface
{
    protected int $indentLevel = 0;
    protected string $indentString = '    ';

    /**
     * Generate a newline with optional indentation
     */
    protected function nl(int $extraIndent = 1): string
    {
        return "\n" . str_repeat($this->indentString, $this->indentLevel + $extraIndent);
    }

    /**
     * Add a comment to the output
     */
    protected function addComment(string $comment): string
    {
        $comment = trim($comment);
        $lines = explode("\n", $comment);
        $output = '';

        foreach ($lines as $line) {
            $output .= $this->nl().'// '.trim($line);
        }

        return $output;
    }

    /**
     * Convert a value to PHP code representation
     */
    protected function convertValue($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            // Check if it's a parameter reference
            if (preg_match('/^%(.+)%$/', $value)) {
                return "'$value'";
            }

            // Check if it's a service reference
            if (str_starts_with($value, '@')) {
                return "service('" . substr($value, 1) . "')";
            }

            // Regular string
            return var_export($value, true);
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }

            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            $items = [];

            foreach ($value as $key => $val) {
                if ($isAssoc) {
                    $items[] = var_export($key, true) . ' => ' . $this->convertValue($val);
                } else {
                    $items[] = $this->convertValue($val);
                }
            }

            return '[' . implode(', ', $items) . ']';
        }

        return var_export($value, true);
    }
    
    /**
     * Escape a string for use in single quotes
     * 
     * @author jrughani9
     */
    protected function escapeString(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }

    /**
     * Get attribute value from a DOMElement
     */
    protected function getAttribute(\DOMElement $element, string $name, $default = null)
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : $default;
    }

    /**
     * Parse boolean attribute
     */
    protected function parseBooleanAttribute(\DOMElement $element, string $name, bool $default = false): bool
    {
        if (!$element->hasAttribute($name)) {
            return $default;
        }

        $value = strtolower($element->getAttribute($name));
        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Get text content from an element
     */
    protected function getTextContent(\DOMElement $element): ?string
    {
        $text = trim($element->textContent);
        return $text !== '' ? $text : null;
    }

    /**
     * Process child nodes including comments
     */
    protected function processChildNodes(\DOMNode $node): string
    {
        $output = '';
        foreach ($node->childNodes as $childNode) {
            // Process comments
            if ($childNode instanceof \DOMComment) {
                $output .= $this->addComment($childNode->nodeValue);
                continue;
            }

            // Skip text nodes (whitespace)
            if ($childNode instanceof \DOMText) {
                continue;
            }

            // Let subclass handle element nodes
            if ($childNode instanceof \DOMElement) {
                $output .= $this->processElement($childNode);
            }
        }

        return $output;
    }

    /**
     * Process an element node - to be implemented by subclasses
     */
    abstract protected function processElement(\DOMElement $element): string;
}
