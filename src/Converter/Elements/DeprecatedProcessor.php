<?php
/**
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

class DeprecatedProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('deprecated');
    }

    public function process(DOMElement $element): string
    {
        $message = trim($element->nodeValue);
        $package = $element->getAttribute('package');
        $version = $element->getAttribute('version');

        return '->deprecate(' .
            $this->formatString($package) . ', ' .
            $this->formatString($version) . ', ' .
            $this->formatString($message) .
        ')';
    }

    /**
     * Format a string value for PHP output (with quotes)
     */
    private function formatString(string $value): string
    {
        if (class_exists($value) || interface_exists($value) || trait_exists($value) || enum_exists($value)) {
            return '\\'.ltrim($value, '\\') . '::class';
        }

        if (str_ends_with($value, '\\')) {
            $value = addcslashes($value, '\'\\');
        } else {
            $value = addcslashes($value, '\'');
        }

        return "'" . $value . "'";
    }
}
