<?php

declare(strict_types=1);

namespace SqlCatalog;

use InvalidArgumentException;
use SqlCatalog\Analysis\EvaluationBudget;

/**
 * What one analysis run is asked to do.
 *
 * @visibility public
 * @example Analyzing with only the Doctrine calls recognised
 *     $options = new \SqlCatalog\AnalysisOptions(['doctrine']);
 *     $options->extensions // => ['doctrine']
 */
final class AnalysisOptions
{
    /**
     * @param list<string> $extensions The extensions whose database calls are recognised
     * @param EvaluationBudget|null $budget How much work one file may cost, or null for the default
     * @param string|null $dialect The framework SQL grammar, or null when not configured
     * @throws InvalidArgumentException When the dialect is unsupported
     */
    public function __construct(
        public readonly array $extensions = ['pdo', 'mysqli'],
        public readonly ?EvaluationBudget $budget = null,
        public readonly ?string $dialect = null,
    ) {
        if ($dialect !== null && !in_array($dialect, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new InvalidArgumentException('Unknown SQL dialect: ' . $dialect);
        }
    }

    /**
     * The same options with a different set of extensions.
     *
     * @param list<string> $extensions
     */
    public function withExtensions(array $extensions): self
    {
        return new self($extensions, $this->budget, $this->dialect);
    }

    /**
     * The budget to run with.
     */
    public function budget(): EvaluationBudget
    {
        return $this->budget ?? new EvaluationBudget();
    }
}
