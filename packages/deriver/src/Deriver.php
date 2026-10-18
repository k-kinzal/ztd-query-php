<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Query;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\SelectorResolution;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Collector;

/**
 * Derives candidate values repeatedly from one captured set of PHP sources.
 * @visibility public
 * @example Preparing source once for independent queries
 *     $path = tempnam(sys_get_temp_dir(), 'deriver-example-');
 *     file_put_contents($path, '<?php function answer() { return 42; } function other() { return 7; }');
 *     $deriver = \Deriver\Deriver::prepare([$path]);
 *     unlink($path);
 *     $deriver->derive('answer#return')[0]->result // => 42
 *     $deriver->derive('other#return')[0]->result // => 7
 */
final class Deriver
{
    /**
     * Keeps the prepared representation and evaluation settings private.
     */
    private function __construct(private readonly SelectorResolution $selection, private readonly CandidateQuery $evaluation)
    {
    }

    /**
     * Captures and parses the selected files without executing application code.
     * Builds reverse indexes for calls, property writes, closure owners and script globals.
     * Function, method and closure bodies are compiled on demand and reused by later queries.
     * The returned instance keeps these contents even if the files later change or disappear.
     * @param list<string> $files Local paths, for example the result of Finder::files()
     * @param DeriverConfig|null $configuration Independent language and evaluation settings; null selects the defaults
     * @throws Exception\CollectorException If a selected file cannot be collected
     * @throws Exception\ParserException If a selected source cannot be parsed
     * @throws Exception\ResolutionException If a selected source cannot be resolved
     * @throws Exception\InvalidInputException If declarations are incomplete or declarations or evaluator registrations conflict
     */
    public static function prepare(array $files, ?DeriverConfig $configuration = null): self
    {
        $settings = $configuration ?? DeriverConfig::default();
        $sources = (new Collector())->collect($files);
        $target = new TargetProfile($settings->phpVersion, $settings->integerBits, $settings->floatPrecision);
        $environment = array_map(Term::fromNative(...), $settings->environment);
        $evaluationConfig = new EvaluatorConfig($target, $environment, $settings->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $settings->evaluators));
        $evaluators = new Evaluators($target, standardEvaluators: $settings->standardEvaluators);
        $identity = new SnapshotIdentity($sources, $evaluationConfig, $evaluators);
        $parser = new Parser(new ParserConfig($settings->phpVersion));
        $resolver = new Resolver();
        $trees = [];
        foreach ($sources as $source) {
            $trees[] = $resolver->resolve($parser->parse($source));
        }
        $program = new Index($identity->id, $trees);
        $diagnostics = $program->diagnostics();
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic->code === 'INCOMPLETE_SOURCE') {
                throw new Exception\InvalidInputException('Malformed captured PHP in ' . $diagnostic->at->path . ': ' . $diagnostic->operation);
            }
        }
        $snapshot = $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $diagnostics));
        return new self(new SelectorResolution($program), new CandidateQuery(new DefinitionIndex($program), $evaluationConfig, $evaluators, $snapshot));
    }

    /**
     * Answers a selector against the prepared sources with fresh evaluation state.
     * Each result owns its evidence; the deriver does not retain query results.
     * @param string $query Callable or source-position selector
     * @return CandidateCollection Candidates and their self-contained evidence
     * @throws Exception\InvalidInputException If the selector cannot identify a supported target
     */
    public function derive(string $query): CandidateCollection
    {
        return $this->evaluation->derive($this->selection->resolve(Query::parse($query)));
    }
}
