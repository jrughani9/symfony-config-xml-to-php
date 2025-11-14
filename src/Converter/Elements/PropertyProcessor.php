<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

class PropertyProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('property');
    }

    public function process(DOMElement $element): string
    {
        $name = $element->getAttribute('name');
        $type = $element->getAttribute('type');
        $id = $element->getAttribute('id');
        
        // Handle service reference
        if ($type === 'service' || $id) {
            $serviceId = $id ?: $this->getTextContent($element);
            $value = "service('" . $serviceId . "')";
        } else {
            $value = $this->convertValue($this->getTextContent($element));
        }
        
        return $this->nl() . '->property(\'' . $name . '\', ' . $value . ')';
    }
}
