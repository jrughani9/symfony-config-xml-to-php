<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;
use GromNaN\SymfonyConfigXmlToPhp\Converter\ValueConversionTrait;

abstract class AbstractElementProcessor implements ElementProcessorInterface
{
    use ValueConversionTrait;
    
    protected string $elementName;

    public function __construct(string $elementName)
    {
        $this->elementName = $elementName;
    }

    public function supports(DOMElement $element): bool
    {
        return $element->nodeName === $this->elementName;
    }

    public function setIndentLevel(int $level): void
    {
        $this->indentLevel = $level;
    }

    public function getIndentLevel(): int
    {
        return $this->indentLevel;
    }
}
