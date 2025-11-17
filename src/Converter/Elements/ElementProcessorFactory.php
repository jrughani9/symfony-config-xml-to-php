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
use GromNaN\SymfonyConfigXmlToPhp\Exception\UnsupportedFeatureException;
use GromNaN\SymfonyConfigXmlToPhp\Converter\WarningCollectorInterface;

class ElementProcessorFactory
{
    /**
     * @var ElementProcessorInterface[]
     */
    private array $processors;

    public function __construct(?WarningCollectorInterface $warningCollector = null)
    {
        $this->processors = [
            new ArgumentProcessor($warningCollector),
            new TagProcessor(),
            new CallProcessor(),
            new PropertyProcessor($warningCollector),
            new BindProcessor(),
            new FactoryProcessor(),
        ];
    }

    public function getProcessor(DOMElement $element): ElementProcessorInterface
    {
        foreach ($this->processors as $processor) {
            if ($processor->supports($element)) {
                return $processor;
            }
        }

        throw UnsupportedFeatureException::forElement($element->nodeName, '');
    }

    /**
     * Set indent level for all processors
     */
    public function setIndentLevel(int $level): void
    {
        foreach ($this->processors as $processor) {
            $processor->setIndentLevel($level);
        }
    }
}
