<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Analysis\Session;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\SyntaxCache;
use JsonException;

/**
 * Opens immutable PHP source snapshots for demand-driven value and state derivation.
 *
 * @visibility public
 * @example Deriving a function return without running its source
 *     $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function answer() { return 42; }')]);
 *     $result = (new \Deriver\Analyzer())->open($input)->derive(new \Deriver\Query\ReturnQuery('answer'));
 *     $result->candidates[0]->result // => 42
 */
final class Analyzer
{
    /**
     * Source-only caches are bounded and shared by sessions opened with this analyzer.
     */
    private readonly SyntaxCache $syntax;
    private readonly GraphCache $lowered;

    /**
     * Creates an analyzer with isolated in-memory source and graph caches.
     */
    public function __construct()
    {
        $this->syntax = new SyntaxCache();
        $this->lowered = new GraphCache();
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
