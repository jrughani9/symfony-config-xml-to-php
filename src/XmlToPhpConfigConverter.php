<?php

/*
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp;

use GromNaN\SymfonyConfigXmlToPhp\Converter\ServiceConverter;
use GromNaN\SymfonyConfigXmlToPhp\Converter\RoutingConverter;
use GromNaN\SymfonyConfigXmlToPhp\Exception\ConversionException;
use GromNaN\SymfonyConfigXmlToPhp\Exception\InvalidXmlException;

class XmlToPhpConfigConverter
{
    private array $converters = [];
    private bool $skipValidation = false;

    public function __construct()
    {
        $this->converters = [
            new ServiceConverter(),
            new RoutingConverter(),
        ];
    }

    /**
     * Set whether to skip validation
     */
    public function setSkipValidation(bool $skipValidation): void
    {
        $this->skipValidation = $skipValidation;
    }

    /**
     * Convert an XML configuration file to PHP configuration
     */
    public function convertFile(string $xmlPath): string
    {
        if (!file_exists($xmlPath)) {
            throw new \RuntimeException(sprintf('File not found: %s', $xmlPath));
        }

        if (!str_ends_with($xmlPath, '.xml')) {
            throw new \RuntimeException('The file must have a .xml extension.');
        }

        // Load and parse the XML
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        
        // Disable errors temporarily to handle invalid XML gracefully
        $previousErrorReporting = libxml_use_internal_errors(true);
        $loaded = $dom->load($xmlPath);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorReporting);
        
        if (!$loaded) {
            throw InvalidXmlException::fromLibXmlErrors($xmlPath, $errors);
        }

        // Find the appropriate converter
        foreach ($this->converters as $converter) {
            if ($converter->supports($dom)) {
                return $converter->convert($dom, $xmlPath);
            }
        }

        throw ConversionException::create(sprintf(
            'No converter found for XML file "%s". The file type is not supported.',
            $xmlPath
        ))->withXmlFile($xmlPath);
    }

    /**
     * Get the PHP filename for a given XML file
     */
    public static function getPhpFilename(string $xmlPath): string
    {
        return preg_replace('/\.xml$/', '.php', $xmlPath);
    }
}
