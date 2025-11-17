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
        $name = $element->getAttribute('name');
        $attributes = [];

        // Collect all attributes except 'name'
        foreach ($element->attributes as $attr) {
            if ($attr->name !== 'name') {
                $attributes[$attr->name] = $attr->value;
            }
        }

        // Also check for child attribute elements (for complex tag attributes)
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === 'attribute') {
                $attrName = $child->getAttribute('name');
                $attrValue = $this->getTextContent($child);
                if ($attrName) {
                    $attributes[$attrName] = $attrValue;
                }
            }
        }

        $output = $this->nl().'->tag(\''.$name.'\'';

        if (!empty($attributes)) {
            $output .= ', '.$this->convertValue($attributes);
        }

        $output .= ')';

        return $output;
    }
}
