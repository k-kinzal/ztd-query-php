<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Analysis\Selection\SelectorResolution;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Result\CandidateCollection;
use Deriver\Session\Session;
use Deriver\Source\Lowering\LoweringCache;
use Deriver\Source\Parsing\ParseCache;
use JsonException;

/**
 * Derives candidates from local PHP files without executing application code.
 *
 * @visibility public
 * @example Deriving a function return without running its source
 *     $path = tempnam(sys_get_temp_dir(), 'deriver-example-');
 *     file_put_contents($path, '<?php function answer() { return 42; }');
 *     $result = (new \Deriver\Analyzer())->analyze([$path], 'answer#return');
 *     unlink($path);
 *     $result->candidates[0]->result // => 42
 */
final class Analyzer
{
    /**
     * Source-only caches are bounded and shared by calls to analyze().
     */
    private readonly ParseCache $syntax;
    private readonly LoweringCache $lowered;

    /**
     * Creates an analyzer with isolated in-memory source and graph caches.
     */
    public function __construct(private readonly Configuration $configuration = new Configuration())
    {
        $this->syntax = new ParseCache();
        $this->lowered = new LoweringCache();
    }

    /**
     * Reads the selected files afresh and answers one query over their captured contents.
     * @param list<string> $files Local file paths, for example the result of Finder::files()
     * @param string $query Callable or source-position selector
     * @return CandidateCollection Candidates and their self-contained evidence
     * @throws Exception\InvalidInputException If files are invalid or the selector cannot identify a supported target
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function analyze(array $files, string $query): CandidateCollection
    {
        $selector = Query::parse($query);
        $session = new Session(ProjectInput::fromFiles($files), $this->configuration, parsing: $this->syntax, lowering: $this->lowered);
        return $session->derive((new SelectorResolution($session->program))->resolve($selector));
    }
}
