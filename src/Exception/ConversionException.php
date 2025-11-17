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

class ConversionException extends \RuntimeException
{
    private ?string $xmlFile = null;
    private ?int $lineNumber = null;
    private ?string $elementName = null;

    public static function create(string $message): self
    {
        return new self($message);
    }

    public function withXmlFile(string $xmlFile): self
    {
        $this->xmlFile = $xmlFile;
        $this->updateMessage();
        return $this;
    }

    public function withLineNumber(int $lineNumber): self
    {
        $this->lineNumber = $lineNumber;
        $this->updateMessage();
        return $this;
    }

    public function withElementName(string $elementName): self
    {
        $this->elementName = $elementName;
        $this->updateMessage();
        return $this;
    }

    private function updateMessage(): void
    {
        $message = $this->message;

        // Strip any previously added context
        if (preg_match('/^(.*?)( \([^)]+\))?$/', $message, $matches)) {
            $message = $matches[1];
        }

        $context = [];

        if ($this->xmlFile) {
            $context[] = sprintf('file: %s', $this->xmlFile);
        }
        if ($this->lineNumber) {
            $context[] = sprintf('line: %d', $this->lineNumber);
        }
        if ($this->elementName) {
            $context[] = sprintf('element: <%s>', $this->elementName);
        }

        if (!empty($context)) {
            $message .= sprintf(' (%s)', implode(', ', $context));
        }

        $this->message = $message;
    }
}
