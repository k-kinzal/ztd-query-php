<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Api\AnalysisSession;
use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\ProjectInput;
use Deriver\Internal\Api\Session;
use JsonException;

/**
 * Opens immutable PHP source snapshots for demand-driven value and state derivation.
 *
 * @visibility public
 * @example Deriving a function return without running its source
 *     $input = new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php function answer() { return 42; }')]);
 *     $result = (new \Deriver\Analyzer())->open($input)->derive(new \Deriver\Api\Query\ReturnQuery('answer'));
 *     $result->normalOutcomes[0]->values['return']->native() // => 42
 */
final class Analyzer
{
    /**
     * Source-only caches are bounded and shared by sessions opened with this analyzer.
     */
    private readonly Internal\Frontend\Php\Cache\SyntaxCache $syntax;
    private readonly Internal\Frontend\Php\Cache\GraphCache $lowered;

    /**
     * Creates an analyzer with isolated in-memory frontend caches.
     */
    public function __construct()
    {
        $this->syntax = new Internal\Frontend\Php\Cache\SyntaxCache();
        $this->lowered = new Internal\Frontend\Php\Cache\GraphCache();
    }

    /**
     * Captures the supplied source world and trusted model configuration.
     * @param ProjectInput $input Explicit source bytes
     * @param Configuration $configuration Semantic target, world, and models
     * @return AnalysisSession Reusable immutable snapshot session
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function open(ProjectInput $input, Configuration $configuration = new Configuration()): AnalysisSession
    {
        return new Session($input, $configuration, $this->syntax, $this->lowered);
    }
}
