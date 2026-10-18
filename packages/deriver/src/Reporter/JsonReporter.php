<?php

declare(strict_types=1);

namespace Deriver\Reporter;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\ReportingException;
use JsonException;
use Override;

/**
 * Encodes candidate values and their ordered evidence paths for command consumers.
 * @visibility public
 * @example Reporting no candidates
 *     $candidates = new \Deriver\Deriver\Candidate\CandidateCollection([], new \Deriver\Deriver\Candidate\ResultRef('r'));
 *     json_decode((new \Deriver\Reporter\JsonReporter())->report($candidates), true)['candidates'] // => []
 */
final class JsonReporter implements Reporter
{
    /**
     * Shared residual nodes use references; evidence is listed once per derivation path.
     * @throws ReportingException If a PHP value cannot be represented by JSON
     */
    #[Override]
    public function report(CandidateCollection $candidates): string
    {
        $rows = [];
        foreach ($candidates as $candidate) {
            $evidence = [];
            foreach ($candidate->evidence as $alternative) {
                $nodes = [];
                foreach ($alternative->nodes() as $node) {
                    $nodes[] = ['id' => $node->id, 'kind' => $node->kind->value, 'inputs' => array_map(static fn (Node $input): string => $input->id, $node->inputs), 'source' => $node->source, 'attributes' => $node->attributes];
                }
                $evidence[] = $nodes;
            }
            $rows[] = ['type' => $candidate->type->value, 'type_name' => $candidate->type_name, 'result' => $candidate->result instanceof Term ? (new TermGraph())->encode($candidate->result) : $candidate->result, 'evidence' => $evidence];
        }
        try {
            return json_encode(['interrupted' => $candidates->interrupted, 'candidates' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
        } catch (JsonException $error) {
            throw new ReportingException('Cannot encode report as JSON: ' . $error->getMessage(), previous: $error);
        }
    }
}
