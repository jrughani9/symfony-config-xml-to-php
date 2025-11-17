<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter\Elements;

use DOMElement;
use GromNaN\SymfonyConfigXmlToPhp\Converter\ValueConversionTrait;

class CollectionProcessor
{
    use ValueConversionTrait;
    
    public function setIndentLevel(int $level): void
    {
        $this->indentLevel = $level;
    }
    
    /**
     * Process collection and return as array
     */
    public function processCollectionAsArray(DOMElement $node, string $itemName): array
    {
        $items = [];
        
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->nodeName === $itemName) {
                $key = $childNode->getAttribute('key');
                $value = $this->getTextContent($childNode);
                
                if ($key !== '' && $value !== null) {
                    // Handle boolean and numeric values
                    if ($value === 'true' || $value === 'false') {
                        $value = $value === 'true';
                    } elseif (is_numeric($value)) {
                        $value = str_contains($value, '.') ? (float) $value : (int) $value;
                    }
                    
                    $items[$key] = $value;
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Process route requirements as array
     */
    public function processRequirementsAsArray(DOMElement $requirementsNode): array
    {
        return $this->processCollectionAsArray($requirementsNode, 'requirement');
    }
    
    /**
     * Process route defaults as array
     */
    public function processDefaultsAsArray(DOMElement $defaultsNode): array
    {
        return $this->processCollectionAsArray($defaultsNode, 'default');
    }
    
    /**
     * Process route options as array
     */
    public function processOptionsAsArray(DOMElement $optionsNode): array
    {
        return $this->processCollectionAsArray($optionsNode, 'option');
    }
}
