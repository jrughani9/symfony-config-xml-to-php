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
use GromNaN\SymfonyConfigXmlToPhp\Converter\WarningCollectorInterface;

class PropertyProcessor extends AbstractElementProcessor
{
    private ?WarningCollectorInterface $warningCollector = null;

    public function __construct(?WarningCollectorInterface $warningCollector = null)
    {
        parent::__construct('property');
        $this->warningCollector = $warningCollector;
    }

    public function process(DOMElement $element): string
    {
        $name = $element->getAttribute('name');
        $type = $element->getAttribute('type');
        $id = $element->getAttribute('id');

        // Check for inline service definition first
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'service') {
                $inlineClass = $child->getAttribute('class');
                if ($inlineClass) {
                    // Add warning about inline service
                    $this->warningCollector?->addWarning(
                        'Inline service definition detected in property',
                        [
                            'property' => $name,
                            'class' => $inlineClass,
                            'note' => 'Inline services are flattened in PHP DSL and may not behave identically',
                        ]
                    );

                    $value = "inline_service('".$this->escapeString($inlineClass)."')";

                    // Process arguments of the inline service if any
                    $argumentProcessor = new ArgumentProcessor($this->warningCollector);
                    $argumentProcessor->setIndentLevel($this->indentLevel);
                    $arguments = [];

                    foreach ($child->childNodes as $argNode) {
                        if ($argNode instanceof \DOMElement && $argNode->nodeName === 'argument') {
                            $arguments[] = $argumentProcessor->process($argNode);
                        }
                    }

                    if (!empty($arguments)) {
                        $value .= '->args(['.implode(', ', $arguments).'])';
                    }

                    return $this->nl().'->property(\''.$name.'\', '.$value.')';
                }
            }
        }

        // Handle service reference
        if ($type === 'service' || $id) {
            $serviceId = $id ?: $this->getTextContent($element);
            $value = "service('".$serviceId."')";
        } else {
            $value = $this->convertValue($this->getTextContent($element));
        }

        return $this->nl().'->property(\''.$name.'\', '.$value.')';
    }
}
