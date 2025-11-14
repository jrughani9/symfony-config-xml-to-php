<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter;

interface ConverterInterface
{
    /**
     * Check if this converter supports the given XML document
     */
    public function supports(\DOMDocument $document): bool;

    /**
     * Convert the XML document to PHP code
     */
    public function convert(\DOMDocument $document, string $xmlPath): string;
}
