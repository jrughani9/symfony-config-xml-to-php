<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;
use GromNaN\SymfonyConfigXmlToPhp\Exception\UnsupportedFeatureException;

class ElementProcessorFactory
{
    /**
     * @var ElementProcessorInterface[]
     */
    private array $processors;

    public function __construct()
    {
        $this->processors = [
            new ArgumentProcessor(),
            new TagProcessor(),
            new CallProcessor(),
            new PropertyProcessor(),
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
