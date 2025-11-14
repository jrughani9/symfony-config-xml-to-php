<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

class FactoryProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('factory');
    }

    public function process(DOMElement $element): string
    {
        $service = $element->getAttribute('service');
        $class = $element->getAttribute('class');
        $method = $element->getAttribute('method');
        $expression = $element->getAttribute('expression');
        
        if ($expression) {
            // Expression factory
            return $this->nl() . '->factory(expr(\'' . $this->escapeString($expression) . '\'))';
        } elseif ($service) {
            // Service factory
            return $this->nl() . '->factory([service(\'' . $service . '\'), \'' . $method . '\'])';
        } elseif ($class) {
            // Static factory
            return $this->nl() . '->factory([\'' . $this->escapeString($class) . '\', \'' . $method . '\'])';
        }
        
        // Inline factory definition (parent element contains the factory config)
        return '';
    }
}
