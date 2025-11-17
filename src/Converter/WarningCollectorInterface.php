<?php

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
