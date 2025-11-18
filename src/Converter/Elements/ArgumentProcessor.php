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
use GromNaN\SymfonyConfigXmlToPhp\Converter\WarningCollectorInterface;

class ArgumentProcessor extends AbstractElementProcessor
{
    private ?WarningCollectorInterface $warningCollector = null;

    public function __construct(?WarningCollectorInterface $warningCollector = null)
    {
        parent::__construct('argument');
        $this->warningCollector = $warningCollector;
    }

    public function process(DOMElement $element): string
    {
        return $this->formatArgument($element);
    }

    /**
     * Format a single argument element
     */
    private function formatArgument(DOMElement $argument): string
    {
        $type = $argument->getAttribute('type') ?: null;
        $value = $argument->nodeValue;

        // Handle nested arguments (collection)
        if (in_array($type, ['collection', null], true)) {
            $items = [];
            foreach ($argument->childNodes as $item) {
                if (!$item instanceof DOMElement) {
                    continue;
                }
                if ($item->nodeName !== $argument->nodeName) {
                    continue;
                }

                $itemKey = $item->getAttribute('key') ?: $item->getAttribute('name');

                // Transform key based on key-type attribute
                $itemKey = match ($item->getAttribute('key-type')) {
                    'constant' => '\\'.ltrim($itemKey, '\\'),
                    'binary' => 'base64_decode('.$this->formatString($itemKey).')',
                    default => $this->formatString($itemKey),
                };

                if ($itemKey) {
                    $items[] = $itemKey . ' => ' . $this->formatArgument($item);
                } else {
                    $items[] = $this->formatArgument($item);
                }
            }

            if ($items) {
                return '[' . implode(', ', $items) . ']';
            }

            // Force empty array for a "collection" type, even if no child nodes
            if ($type === 'collection') {
                return '[]';
            }
        }

        // Inline services are defined with a nested <service> element
        foreach ($argument->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->nodeName === 'service') {
                return $this->processInlineService($childNode);
            }
        }

        // Handle specific argument types
        if ($type === 'service' || $type === 'service_closure') {
            return $this->processServiceReference($argument, $type);
        }

        if ($type === 'closure') {
            if ($argument->hasAttribute('id')) {
                return 'closure(' . $this->processServiceReference($argument, 'service') . ')';
            }

            return 'closure(' . ($this->formatArguments($argument) ?? '[]') . ')';
        }

        if ($type === 'expression') {
            return "expr({$this->formatString($value)})";
        }

        if ($type === 'string') {
            return $this->formatString($value);
        }

        if ($type === 'constant') {
            return '\\'.ltrim($value, '\\');
        }

        if ($type === 'binary') {
            return 'base64_decode('.$this->formatString($value).')';
        }

        if ($type === 'tagged' || $type === 'tagged_iterator') {
            return $this->processTagged('tagged_iterator', $argument);
        }

        if ($type === 'tagged_locator') {
            return $this->processTagged('tagged_locator', $argument);
        }

        if ($type === 'service_locator') {
            return $this->processServiceLocator($argument);
        }

        if ($type === 'iterator') {
            return 'iterator(' . ($this->formatArguments($argument) ?? '[]') . ')';
        }

        if ($type === 'abstract') {
            return 'abstract_arg('.$this->formatString($value).')';
        }

        if ($type === null) {
            // Default handling (treat as string or convert to appropriate PHP value)
            return $this->formatValue($value);
        }

        throw new \RuntimeException(sprintf('Unsupported argument type: %s', $type));
    }

    /**
     * Format a list of arguments from element's child argument nodes
     */
    private function formatArguments(DOMElement $element): ?string
    {
        /** @var DOMElement[] $arguments */
        $arguments = array_filter(iterator_to_array($element->childNodes), fn(\DOMNode $node) => $node instanceof DOMElement && $node->nodeName === 'argument');

        if (count($arguments) === 0) {
            return null;
        }

        // If there's only one argument, use ->args([...])
        if (count($arguments) === 1) {
            $arg = current($arguments);
            $key = $arg->getAttribute('key');
            if ($arg->hasAttribute('index')) {
                $key = 'index_'.$arg->getAttribute('index');
            }
            if ($key) {
                return '[' . $this->formatString($key) . ' => ' . $this->formatArgument($arg) . ']';
            }

            return '[' . $this->formatArgument($arg) . ']';
        }

        $output = '[';
        $this->indentLevel++;
        foreach ($arguments as $arg) {
            if (!$arg instanceof DOMElement) {
                continue;
            }
            $key = $arg->getAttribute('key');
            if ($arg->hasAttribute('index')) {
                $key = $arg->getAttribute('index');
            }
            if ($key) {
                $output .= $this->nl() . $this->formatString($key) . ' => ' . $this->formatArgument($arg) . ',';
            } else {
                $output .= $this->nl() . $this->formatArgument($arg) . ',';
            }
        }
        $this->indentLevel--;

        return $output . $this->nl().']';
    }

    /**
     * Process a service reference (service or service_closure)
     */
    private function processServiceReference(DOMElement $element, string $type): string
    {
        $id = $element->getAttribute('id');
        $output = $type.'('.$this->formatString($id).')';

        $onInvalid = $element->getAttribute('on-invalid') ?: 'exception';
        $output .= match ($onInvalid) {
            'ignore' => '->ignoreOnInvalid()',
            'null' => '->nullOnInvalid()',
            'ignore_uninitialized' => '->ignoreOnUninitialized()',
            'exception' => '',
        };

        return $output;
    }

    /**
     * Process inline service definition
     */
    private function processInlineService(DOMElement $service): string
    {
        $class = $service->getAttribute('class') ?: throw new \LogicException('Inline service must have a class attribute.');
        $output = sprintf('inline_service(%s)', $this->formatString($class));

        // Process service configuration (arguments, properties, calls, etc.)
        $this->indentLevel++;
        
        // Handle arguments
        $arguments = $this->formatArguments($service);
        if ($arguments !== null) {
            $output .= $this->nl() . '->args(' . $arguments . ')';
        }

        // Handle lazy attribute
        if ($service->hasAttribute('lazy')) {
            $lazy = $service->getAttribute('lazy');
            if ($lazy === 'true' || $lazy === '1') {
                $output .= $this->nl().'->lazy()';
            } else {
                $output .= $this->nl().'->lazy(' . $this->formatString($lazy) . ')';
            }
        }

        // Handle properties
        foreach ($service->childNodes as $childNode) {
            if (!($childNode instanceof DOMElement)) {
                continue;
            }

            if ($childNode->nodeName === 'property') {
                $propName = $childNode->getAttribute('key') ?: $childNode->getAttribute('name');
                $output .= $this->nl() . '->property('.$this->formatString($propName).', '.$this->formatArgument($childNode).')';
            }
        }

        $this->indentLevel--;

        return $output;
    }

    /**
     * Process tagged_iterator or tagged_locator argument
     */
    private function processTagged(string $method, DOMElement $argument): string
    {
        $output = $method.'(' . $this->formatString($argument->getAttribute('tag'));

        if ($argument->hasAttribute('index-by')) {
            $output .= ', indexAttribute: ' . $this->formatString($argument->getAttribute('index-by'));
        }

        if ($argument->hasAttribute('default-index-method')) {
            $output .= ', defaultIndexMethod: ' . $this->formatString($argument->getAttribute('default-index-method'));
        }

        if ($argument->hasAttribute('default-priority-method')) {
            $output .= ', defaultPriorityMethod: ' . $this->formatString($argument->getAttribute('default-priority-method'));
        }

        // Exclude can be an attribute or multiple <exclude> child elements
        $exclude = [];
        if ($argument->hasAttribute('exclude')) {
            $exclude[] = $this->formatString($argument->getAttribute('exclude'));
        }
        foreach ($argument->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->nodeName === 'exclude') {
                $exclude[] = $this->formatString($childNode->nodeValue);
            }
        }
        if ($exclude) {
            $output .= ', exclude: ' . match(count($exclude)) {
                1 => current($exclude),
                default => '[' . implode(', ', $exclude) . ']',
            };
        }

        if ($argument->hasAttribute('exclude-self')) {
            $excludeSelf = $argument->getAttribute('exclude-self') === 'true' ? 'true' : 'false';
            $output .= ', excludeSelf: ' . $excludeSelf;
        }

        return $output . ')';
    }

    /**
     * Process service_locator argument
     */
    private function processServiceLocator(DOMElement $argument): string
    {
        $output = 'service_locator([';

        $this->indentLevel++;
        foreach ($argument->childNodes as $item) {
            if (!$item instanceof DOMElement || $item->nodeName !== 'argument') {
                continue;
            }

            $itemKey = $item->getAttribute('key');
            if ($itemKey) {
                $output .= $this->nl() . $this->formatString($itemKey) . ' => ' . $this->formatArgument($item) . ',';
            } else {
                $output .= $this->nl() . $this->formatArgument($item) . ',';
            }
        }
        $this->indentLevel--;

        return $output . $this->nl().'])';
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

        // Check if it's a class constant reference
        if (preg_match('/^[\w\\\\]+::[A-Z_]+$/', $value)) {
            return '\\'.ltrim($value, '\\');
        }

        // Regular string
        return $this->formatString($value);
    }
}
