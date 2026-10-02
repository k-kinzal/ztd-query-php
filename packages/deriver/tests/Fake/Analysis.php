<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\AnalysisSession;
use Deriver\Analyzer;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use JsonException;

/**
 * Independent source fixtures enter through the public library API.
 * @visibility root
 */
final class Analysis
{
    /**
     * Opens one immutable in-memory fixture.
     * @param string $source PHP source, including its opening tag
     * @param Configuration $configuration Explicit semantic assumptions
     * @return AnalysisSession Open session
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function session(string $source, Configuration $configuration = new Configuration()): AnalysisSession
    {
        return (new Analyzer())->open(new ProjectInput([new SourceFile('fixture.php', $source)]), $configuration);
    }

    /**
     * Derives a fixture's target function return.
     * @param string $source PHP source
     * @param string $symbol Callable name
     * @return DerivationResult Symbolic result
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function returns(string $source, string $symbol = 'target'): DerivationResult
    {
        return self::session($source)->derive(new ReturnQuery($symbol));
    }

    /**
     * Derives the first argument of the first call to a sink function.
     * @param string $source PHP source, including its opening tag and the sink declaration
     * @param string $sink Called function
     * @return DerivationResult Symbolic argument observation
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function argument(string $source, string $sink = 'sink'): DerivationResult
    {
        $session = self::session($source);
        return $session->derive(new ValueQuery($session->callsTo($sink)[0]->argument(0)));
    }

    /**
     * Selects the frontier recorded for one operation.
     * @param DerivationResult $result Derived observation
     * @param string $operation Frontier operation
     * @return Frontier|null The frontier, or null when the operation recorded none
     */
    public static function frontier(DerivationResult $result, string $operation): ?Frontier
    {
        foreach ($result->frontiers as $frontier) {
            if ($frontier->operation === $operation) {
                return $frontier;
            }
        }
        return null;
    }
}
