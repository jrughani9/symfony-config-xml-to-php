<?php

namespace GromNaN\SymfonyConfigXmlToPhp\Converter;

abstract class AbstractConverter implements ConverterInterface
{
    use ValueConversionTrait;

    /**
     * Add a comment to the output
     */
    protected function addComment(string $comment): string
    {
        $comment = trim($comment);
        $lines = explode("\n", $comment);
        $output = '';

        foreach ($lines as $line) {
            $output .= $this->nl().'// '.trim($line);
        }

        return $output;
    }

    /**
     * Process child nodes including comments
     */
    protected function processChildNodes(\DOMNode $node): string
    {
        $output = '';
        foreach ($node->childNodes as $childNode) {
            // Process comments
            if ($childNode instanceof \DOMComment) {
                $output .= $this->addComment($childNode->nodeValue);
                continue;
            }

            // Skip text nodes (whitespace)
            if ($childNode instanceof \DOMText) {
                continue;
            }

            // Let subclass handle element nodes
            if ($childNode instanceof \DOMElement) {
                $output .= $this->processElement($childNode);
            }
        }

        return $output;
    }

    /**
     * Process an element node - to be implemented by subclasses
     */
    abstract protected function processElement(\DOMElement $element): string;
}
