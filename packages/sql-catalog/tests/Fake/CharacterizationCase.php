<?php

declare(strict_types=1);

namespace Tests\Fake;

use JsonException;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Core\Extension\UnknownExtensionException;
use SqlCatalog\Facade\AnalysisOptions;
use SqlCatalog\Facade\Analyzer;
use SqlCatalog\Facade\Builtins;
use SqlCatalog\Facade\Configuration;
use SqlCatalog\Reporter\Json\JsonReporter;

/**
 * Sources, the options they are analyzed with, and the JSON report they produced when recorded.
 */
final class CharacterizationCase
{
    /**
     * @param array<string, string> $sources Source text, keyed by the path the catalog reports
     * @param list<string> $extensions The extensions whose calls are recognised
     * @param string|null $dialect The framework SQL grammar
     * @param array{int, int, int}|null $budget The step, depth and loop-pass limits, or null for the default
     * @param list<array{string, string, string|null, string}> $probe Application calls read as queries
     * @param array<string, string> $functionModels Function names mapped to model callables
     * @param string $expected The recorded report, as pretty-printed JSON
     */
    public function __construct(
        public readonly array $sources,
        public readonly array $extensions,
        public readonly ?string $dialect,
        public readonly ?array $budget,
        public readonly array $probe,
        public readonly array $functionModels,
        public readonly string $expected,
    ) {
    }

    /**
     * The report the analyzer produces for the sources now, as pretty-printed JSON.
     *
     * @throws JsonException When the report cannot be encoded
     * @throws UnknownExtensionException When the case names an extension that is not registered
     */
    public function report(): string
    {
        $extensions = Builtins::extensions();
        $extensions->register(new ProbeExtension($this->probe));
        $budget = $this->budget === null ? null : new EvaluationBudget(...$this->budget);
        $catalog = (new Analyzer($extensions))
            ->withConfiguration(new Configuration([], $this->functionModels))
            ->analyzeSource($this->sources, new AnalysisOptions($this->extensions, $budget, $this->dialect));

        return self::encode((new JsonReporter())->toArray($catalog));
    }

    /**
     * A report as the corpus stores it.
     *
     * @throws JsonException When the report cannot be encoded
     */
    public static function encode(mixed $report): string
    {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
