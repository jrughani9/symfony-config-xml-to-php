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

        foreach ($element->childNodes as $node) {
            if ($node instanceof DOMElement && $node->nodeName === 'argument') {
                $arguments[] = $argumentProcessor->process($node);
            }
        }

        $output = $this->nl().'->call(\''.$method.'\'';

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
}
