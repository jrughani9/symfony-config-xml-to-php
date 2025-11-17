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
