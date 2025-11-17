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
