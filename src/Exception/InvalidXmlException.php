<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Exception;

class InvalidXmlException extends ConversionException
{
    public static function fromLibXmlErrors(string $xmlPath, array $errors): self
    {
        $errorMessages = array_map(fn($error) => trim($error->message), $errors);
        $message = sprintf(
            'Failed to parse XML file "%s": %s',
            $xmlPath,
            implode('; ', $errorMessages)
        );

        return (new self($message))->withXmlFile($xmlPath);
    }
}
