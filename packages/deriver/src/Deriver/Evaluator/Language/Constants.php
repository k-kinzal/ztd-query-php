<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Language;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Guards;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Resolves target-profile constants and source definitions.
 * @visibility parent
 */
final class Constants
{
    /**
     * Uses the same dependency engine as ordinary expressions.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Looks up an exact constant spelling without importing host environment values.
     */
    public function value(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $names = [$instruction->name];
        $fallback = $instruction->attributes['fallback'] ?? '';
        if (is_string($fallback) && $fallback !== '' && $fallback !== $instruction->name) {
            $names[] = $fallback;
        }
        foreach ($names as $name) {
            $body = $this->engine->context->index->program->constant($name);
            if ($body !== null) {
                return $this->engine->returns(new Frame(new \Deriver\Deriver\Evaluator\Index\BodyIndex($body), 'constant:' . $name), $depth);
            }
            $value = $this->named($name, $depth);
            if ($value !== null) {
                return $value;
            }
        }
        return $this->engine->context->reference($frame, $instruction->name, $instruction->source, reason: 'MISSING_CONSTANT');
    }

    /**
     * Resolves an exact spelling before trying PHP's unqualified global fallback.
     */
    public function named(string $name, int $depth): ?Term
    {
        $context = $this->engine->context;
        $value = match ($name) {
            'PHP_INT_MAX' => Term::constant(9223372036854775807),
            'PHP_INT_MIN' => Term::constant(-9223372036854775807 - 1),
            'PHP_INT_SIZE' => Term::constant(8),
            default => null,
        };
        if ($value !== null) {
            return $value;
        }
        $values = [];
        foreach ($context->index->callers('define') as [$graph, $call]) {
            if ($context->work() !== null) {
                $owner = new Frame($graph, 'source:' . $graph->body->symbol);
                $values[] = [$context->reference($owner, $name, $call->source, reason: $context->stopReason ?? 'BUDGET_EXCEEDED'), []];
                break;
            }
            if (count($call->arguments) < 2) {
                continue;
            }
            $owner = new Frame($graph, 'source:' . $graph->body->symbol);
            $defined = $this->engine->value($owner, $call->arguments[0]->register, $depth);
            if ($defined->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $defined->literal === $name) {
                $value = $this->engine->value($owner, $call->arguments[1]->register, $depth);
                $values[] = [(new Guards($this->engine))->at($owner, $graph->positions[$call->result][0], $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::SourceDefinition, $call->source, ['owner' => $graph->body->symbol, 'definition' => $call->result, 'operation' => 'define']), $depth), []];
            }
        }
        return $values === [] ? null : (new Choices($this->engine->context->provenance))->make($values);
    }
}
