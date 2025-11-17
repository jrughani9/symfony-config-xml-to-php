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

interface WarningCollectorInterface
{
    /**
     * Add a warning message with optional context
     */
    public function addWarning(string $message, array $context = []): void;

    /**
     * Get all collected warnings
     *
     * @return array<int, array{message: string, context: array}>
     */
    public function getWarnings(): array;

    /**
     * Check if there are any warnings
     */
    public function hasWarnings(): bool;

    /**
     * Clear all warnings
     */
    public function clear(): void;
}
