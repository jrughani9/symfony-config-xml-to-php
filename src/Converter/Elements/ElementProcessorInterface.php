<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

interface ElementProcessorInterface
{
    /**
     * Process a DOM element and return the PHP code representation
     */
    public function process(DOMElement $element): string;

    /**
     * Check if this processor can handle the given element
     */
    public function supports(DOMElement $element): bool;

    /**
     * Set the indentation level for generated code
     */
    public function setIndentLevel(int $level): void;

    /**
     * Get the current indentation level
     */
    public function getIndentLevel(): int;
}
