<?php
/**
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp\Exception;

class UnsupportedFeatureException extends ConversionException
{
    public static function forElement(string $elementName, string $xmlPath): self
    {
        $message = sprintf('Element "%s" is not supported by the converter', $elementName);
        return (new self($message))
            ->withElementName($elementName)
            ->withXmlFile($xmlPath);
    }

    public static function forAttribute(string $attributeName, string $elementName, string $xmlPath): self
    {
        $message = sprintf('Attribute "%s" on element "%s" is not supported', $attributeName, $elementName);
        return (new self($message))
            ->withElementName($elementName)
            ->withXmlFile($xmlPath);
    }
}
