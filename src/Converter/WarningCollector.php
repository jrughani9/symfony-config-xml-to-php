<?php
/**
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp\Converter;

class WarningCollector implements WarningCollectorInterface
{
    /**
     * @var array<int, array{message: string, context: array}>
     */
    private array $warnings = [];

    public function addWarning(string $message, array $context = []): void
    {
        $this->warnings[] = [
            'message' => $message,
            'context' => $context,
        ];
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function clear(): void
    {
        $this->warnings = [];
    }

    /**
     * Get formatted warnings for display
     *
     * @return string[]
     */
    public function getFormattedWarnings(): array
    {
        $formatted = [];

        foreach ($this->warnings as $warning) {
            $message = $warning['message'];

            if (!empty($warning['context'])) {
                $contextParts = [];
                foreach ($warning['context'] as $key => $value) {
                    $contextParts[] = sprintf('%s: %s', $key, $value);
                }
                $message .= ' ('.implode(', ', $contextParts).')';
            }

            $formatted[] = $message;
        }

        return $formatted;
    }
}
