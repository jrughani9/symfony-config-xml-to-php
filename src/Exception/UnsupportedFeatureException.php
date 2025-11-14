<?php

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
