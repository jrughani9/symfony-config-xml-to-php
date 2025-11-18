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

class CallProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('call');
    }

    public function process(DOMElement $element): string
    {
        $method = $element->getAttribute('method');
        $arguments = [];
        $returnsClone = $this->parseBooleanAttribute($element, 'returns-clone');

        $argumentProcessor = new ArgumentProcessor();
        $argumentProcessor->setIndentLevel($this->indentLevel);

        // Collect arguments preserving their keys
        /** @var DOMElement[] $argumentElements */
        $argumentElements = array_filter(iterator_to_array($element->childNodes), fn(\DOMNode $node) => $node instanceof DOMElement && $node->nodeName === 'argument');

        foreach ($argumentElements as $argNode) {
            $key = $argNode->getAttribute('key');
            $argValue = $argumentProcessor->process($argNode);
            
            if ($key) {
                $arguments[] = $this->formatString($key) . ' => ' . $argValue;
            } else {
                $arguments[] = $argValue;
            }
        }

        $output = $this->nl().'->call('.$this->formatString($method);

        if (!empty($arguments)) {
            $output .= ', ['.implode(', ', $arguments).']';
            if ($returnsClone) {
                // When arguments exist, add returnsClone as third positional parameter
                $output .= ', true';
            }
        } elseif ($returnsClone) {
            // When no arguments but returnsClone is true, use named parameter
            $output .= ', returnsClone: true';
        }

        $output .= ')';

        return $output;
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
}
