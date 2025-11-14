<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

class BindProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('bind');
    }

    public function process(DOMElement $element): string
    {
        $key = $element->getAttribute('key');
        $type = $element->getAttribute('type');
        $id = $element->getAttribute('id');
        
        // Handle different types of bindings
        if ($type === 'service' || $id) {
            $value = "service('" . ($id ?: $this->getTextContent($element)) . "')";
        } elseif ($type === 'tagged_iterator') {
            $tag = $element->getAttribute('tag');
            $value = "tagged_iterator('" . $tag . "')";
        } else {
            $value = $this->convertValue($this->getTextContent($element));
        }
        
        return $this->nl() . '->bind(\'' . $key . '\', ' . $value . ')';
    }
}
