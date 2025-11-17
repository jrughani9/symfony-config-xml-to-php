<?php
/**
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
