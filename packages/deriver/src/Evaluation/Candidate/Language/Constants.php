<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Evidence\Provenance;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Guards;
use Deriver\Value\Term;

/**
 * Separates target-profile constants, explicit environment values and source definitions.
 * @visibility root
 */
final class Constants
{
    /**
     * Uses the same dependency engine as ordinary expressions.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**
     * Looks up an exact constant spelling without importing host environment values.
     */
    public function value(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $context = $this->engine->context;
        $value = $context->configuration->environment['constant:' . $instruction->name] ?? match ($instruction->name) {
            'PHP_INT_MAX' => Term::constant(9223372036854775807),
            'PHP_INT_MIN' => Term::constant(-9223372036854775807 - 1),
            'PHP_INT_SIZE' => Term::constant(8),
            default => null,
        };
        if ($value !== null) {
            return Provenance::wrap($value, 'external-input', $instruction->source, ['input' => 'constant:' . $instruction->name, 'version' => $context->configuration->environmentVersion, 'context' => $frame->identity]);
        }
        $values = [];
        foreach ($context->index->callers('define') as [$graph, $call]) {
            if ($context->work() !== null) {
                $values[] = [$context->reference($frame, $instruction->name, $instruction->source, reason: $context->stopReason ?? 'BUDGET_EXCEEDED'), []];
                break;
            }
            if (count($call->arguments) < 2) {
                continue;
            }
            $owner = new Frame($graph, 'source:' . $graph->body->symbol);
            $name = $this->engine->value($owner, $call->arguments[0]->register, $depth);
            if ($name->kind === 'constant' && $name->literal === $instruction->name) {
                $value = $this->engine->value($owner, $call->arguments[1]->register, $depth);
                $values[] = [(new Guards($this->engine))->at($owner, $graph->positions[$call->result][0], Provenance::wrap($value, 'source-definition', $call->source, ['owner' => $graph->body->symbol, 'definition' => $call->result, 'operation' => 'define']), $depth), []];
            }
        }
        return $values === [] ? $context->reference($frame, $instruction->name, $instruction->source, reason: 'MISSING_CONSTANT') : (new Choices())->make($values);
    }
}
