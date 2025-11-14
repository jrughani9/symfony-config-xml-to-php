<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;

class ArgumentProcessor extends AbstractElementProcessor
{
    public function __construct()
    {
        parent::__construct('argument');
    }

    public function process(DOMElement $element): string
    {
        $type = $element->getAttribute('type');
        $key = $element->getAttribute('key');
        $id = $element->getAttribute('id');
        
        // Service reference
        if ($type === 'service' || $id) {
            return "service('" . ($id ?: $this->getTextContent($element)) . "')";
        }
        
        // Tagged services
        if ($type === 'tagged_iterator' || $type === 'tagged') {
            $tag = $element->getAttribute('tag');
            $options = [];
            
            if ($element->hasAttribute('index-by')) {
                $options[] = sprintf("'index_by' => '%s'", $element->getAttribute('index-by'));
            }
            if ($element->hasAttribute('default-index-method')) {
                $options[] = sprintf("'default_index_method' => '%s'", $element->getAttribute('default-index-method'));
            }
            if ($element->hasAttribute('default-priority-method')) {
                $options[] = sprintf("'default_priority_method' => '%s'", $element->getAttribute('default-priority-method'));
            }
            
            if (empty($options)) {
                return "tagged_iterator('" . $tag . "')";
            }
            
            return sprintf("tagged_iterator('%s', [%s])", $tag, implode(', ', $options));
        }

        // Tagged locator
        if ($type === 'tagged_locator') {
            $tag = $element->getAttribute('tag');
            $options = [];
            
            if ($element->hasAttribute('index-by')) {
                $options[] = sprintf("'index_by' => '%s'", $element->getAttribute('index-by'));
            }
            
            if (empty($options)) {
                return "tagged_locator('" . $tag . "')";
            }
            
            return sprintf("tagged_locator('%s', [%s])", $tag, implode(', ', $options));
        }

        // Service locator
        if ($type === 'service_locator') {
            $services = [];
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->nodeName === 'argument') {
                    $serviceKey = $child->getAttribute('key');
                    $serviceId = $child->getAttribute('id');
                    if ($serviceKey && $serviceId) {
                        $services[] = sprintf("'%s' => service('%s')", $serviceKey, $serviceId);
                    }
                }
            }
            return 'service_locator([' . implode(', ', $services) . '])';
        }
        
        // Collection
        if ($type === 'collection') {
            $items = [];
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->nodeName === 'argument') {
                    $childProcessor = new self();
                    $childProcessor->setIndentLevel($this->indentLevel);
                    $childKey = $child->getAttribute('key');
                    $childValue = $childProcessor->process($child);
                    
                    if ($childKey !== '') {
                        $items[] = var_export($childKey, true) . ' => ' . $childValue;
                    } else {
                        $items[] = $childValue;
                    }
                }
            }
            return '[' . implode(', ', $items) . ']';
        }

        // Constant
        if ($type === 'constant') {
            $constantName = $this->getTextContent($element);
            return sprintf("constant('%s')", $constantName);
        }

        // Binary
        if ($type === 'binary') {
            $binaryContent = $this->getTextContent($element);
            return sprintf("binary('%s')", $binaryContent);
        }

        // Expression
        if ($type === 'expression') {
            $expression = $this->getTextContent($element);
            return sprintf("expr('%s')", $expression);
        }
        
        // Regular value
        $value = $this->getTextContent($element);
        return $this->convertValue($value);
    }
}
