<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Closure;
use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Evaluates the dependencies of one selected observation through cooperating evaluators.
 * Live alternatives share a work queue; completed and irreducible branches retain
 * their values, while interrupted branches keep their remaining dependencies.
 * @visibility namespace
 * @visibility Deriver\Deriver\Publication
 */
final class Evaluator
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Scope\Context $context)
    {
    }

    /**
     * Derives the candidate expression graph for one selected observation.
     */
    public function evaluate(Target $target): Term
    {
        $demand = new Demand($this);
        foreach ($this->context->entries as $entry) {
            $graph = $this->context->index->graph($entry->symbol);
            if ($graph !== null) {
                $this->context->entryFrames[strtolower($entry->symbol)] = $demand->frames($graph, $this->context->entries);
            }
        }
        return $demand->select($target);
    }

    /**
     * Expands the requested dependency and retains unresolved children.
     */
    public function value(Scope\Frame $frame, string $register, int $depth): Term
    {
        $key = $frame->identity . ':' . $register;
        if (isset($this->context->values[$key])) {
            $this->context->sharedNodeHits++;
            return $this->context->values[$key];
        }
        return $this->context->search->resolve(
            $key,
            fn (): Term => (new Expression\DefinitionEvaluator($this))->evaluate($frame, $register, $depth),
            fn (): Term => $this->context->reference($frame, $register, $frame->graph->body->source, reason: 'CYCLE', kind: TermKind::Recursive),
        );
    }

    /**
     * Keeps unresolved dependency reasons in the evidence.
     */
    public function bounded(Scope\Frame $frame, Instruction $instruction, Term $value): Term
    {
        if (isset($value->attributes['reason']) && $this->context->provenance->get($value)?->kind !== Evidence\EvidenceKind::Unexpanded) {
            $value = $this->context->provenance->wrap($value, Evidence\EvidenceKind::Unexpanded, $instruction->source, ['symbol' => (string) $value->literal, 'owner' => $frame->graph->body->symbol, 'context' => $frame->identity, 'reason' => $value->attributes['reason']]);
        }
        return $value;
    }

    /**
     * Dispatches one demanded definition without advancing program state.
     */
    public function instruction(Scope\Frame $frame, Instruction $instruction, int $depth): Term
    {
        if (($reason = $this->context->work()) !== null) {
            return (new Enumeration\Suspension())->expression($this->context, $frame, $instruction, $reason);
        }
        return (new Micro\Dispatch())->evaluate(new Micro\Request($this, $frame, $instruction, $depth), new Micro\InstructionEvaluator());
    }

    /**
     * Expands ordinary expression operands through the same candidate machinery.
     */
    public function expression(Scope\Frame $frame, Instruction $instruction, int $depth): Term
    {
        $op = $instruction->operation;
        $values = $this->context->search->all(array_map(fn (string $operand): Closure => fn (): Term => $this->value($frame, $operand, $depth), $instruction->operands));
        if ($op === 'intrinsic') {
            $callback = (new Language\Callbacks())->apply($this, $frame, $instruction, $values, $depth);
            if ($callback !== null) {
                return $this->context->provenance->operation($callback, $values, $instruction->name);
            }
        }
        if ($op === 'cast' && strtolower($instruction->name) === 'string' || $op === 'binary' && $instruction->name === '.') {
            $values = array_map(fn (Term $value): Term => (new Language\Expressions($this))->string($frame, $instruction, $value, $depth), $values);
        }
        if ($op === 'cast' && strtolower($instruction->name) === 'object') {
            return (new Language\Expressions($this))->object($frame, $instruction, $values[0]);
        }
        $value = $this->operation($op, $instruction->name, $values);
        return $value->kind === TermKind::Operation ? new Term($value->kind, $value->literal, $value->operands, [...$value->attributes, 'source' => $instruction->source->path, 'start' => $instruction->source->start, 'end' => $instruction->source->end]) : $value;
    }

    /**
     * Collects return expressions and their value-selecting conditions.
     */
    public function returns(Scope\Frame $frame, int $depth): Term
    {
        return (new Expression\ReturnEvaluator($this))->evaluate($frame, $depth);
    }

    /**
     * Selects expression alternatives using the corresponding condition.
     */
    public function phi(Scope\Frame $frame, Instruction $instruction, int $depth): Term
    {
        return (new Expression\ConditionalEvaluator($this))->evaluate($frame, $instruction, $depth);
    }

    /**

     * @param list<Term> $values

     */
    public function operation(string $operation, string $name, array $values): Term
    {
        return (new Enumeration\Choices($this->context->provenance))->apply($operation . ':' . $name, $values, fn (array $operands): Term => $this->reduce($operation, $name, $operands), $this->context->budget->partitions);
    }

    /**

     * @param list<Term> $values

     */
    public function reduce(string $operation, string $name, array $values): Term
    {
        foreach ($values as $value) {
            if ($value->kind === TermKind::Throwable) {
                return $value;
            }
        }
        $operations = new Operations($this->context->configuration->target->floatPrecision);
        return match ($operation) {
            'binary' => $operations->binary($name, $values[0], $values[1]),
            'unary' => $operations->unary($name, $values[0]),
            'cast' => $operations->cast($name, $values[0]),
            'print' => Term::constant(1),
            'not-null' => $values[0]->kind === TermKind::Constant ? Term::constant($values[0]->literal !== null) : new Term(TermKind::Operation, 'not-null', $values),
            'array-read' => $this->element($values[0], $values[1]),
            'array-set' => in_array($values[1]->kind, [TermKind::Constant, TermKind::Append], true) ? (new Scalar\Arrays())->set($values[0], $values[1]->kind === TermKind::Append ? null : $values[1], $values[2]) : new Term(TermKind::ArraySet, operands: $values, attributes: ['type' => 'array']),
            'intrinsic' => $this->intrinsic($name, $values),
            'throw' => new Term(TermKind::Throwable, (string) ($values[0]->attributes['type'] ?? 'Throwable'), $values),
            'external', 'external-body' => new Term(TermKind::Operation, $name !== '' ? $name : $operation, $values, ['reason' => 'EXTERNAL_INPUT']),
            default => new Term(TermKind::Operation, $operation . ':' . $name, $values, ['reason' => 'UNSUPPORTED_OPERATION']),
        };
    }

    /**
     * Reads a known array key or retains the indexing expression.
     */
    public function element(Term $array, Term $key): Term
    {
        $key = (new Operations())->arrayKey($key);
        if ($array->kind === TermKind::Constant && is_string($array->literal) && $key->kind === TermKind::Constant && is_int($key->literal)) {
            return isset($array->literal[$key->literal]) ? Term::constant($array->literal[$key->literal]) : new Term(TermKind::ArrayRead, operands: [$array, $key], attributes: ['reason' => 'MISSING_OFFSET']);
        }
        if ($array->kind === TermKind::Array && $key->kind === TermKind::Constant && (is_int($key->literal) || is_string($key->literal))) {
            return $array->operands[$key->literal] ?? Term::constant(null);
        }
        return new Term(TermKind::ArrayRead, operands: [$array, $key]);
    }

    /**

     * @param list<Term> $values

     */
    public function intrinsic(string $name, array $values): Term
    {
        if ($name === 'is_callable' && ($values[1]->literal ?? false) === false && ($values[2]->kind ?? TermKind::Omitted) === TermKind::Omitted) {
            $value = $values[0];
            if (in_array($value->kind, [TermKind::Closure, TermKind::Callable, TermKind::CallableMethod], true) || $value->kind === TermKind::Constant && is_string($value->literal) && ($this->context->index->graph($value->literal) !== null || (new Builtin\Library())->evaluator($value->literal) !== null)) {
                return Term::constant(true);
            }
        }
        if ($name === 'getenv') {
            $supplied = (new Expression\EnvironmentEvaluator($this->context->configuration->environment))->evaluate($values);
            if ($supplied !== null) {
                return $supplied;
            }
        }
        $intrinsic = $this->context->evaluators->extensions->intrinsics[$name] ?? null;
        $result = $intrinsic === null ? (new ScalarFunctions($this->context->configuration->target->floatPrecision))->apply($name, $values) : (new Intrinsic\IntrinsicEvaluation())->evaluate($intrinsic, $values, $this->context->configuration->target);
        return $result->kind === TermKind::Opaque ? new Term(TermKind::Operation, 'intrinsic:' . $name, $values, ['reason' => in_array($name, ['time', 'getenv', 'random_int', 'rand', 'mt_rand', 'microtime'], true) ? 'EXTERNAL_INPUT' : 'UNSUPPORTED_OPERATION']) : $result;
    }

    /**
     * Resolves captured constant definitions without executing application code.
     */
    public function constant(Scope\Frame $frame, Instruction $instruction, int $depth): Term
    {
        $name = strtolower($instruction->name);
        if (in_array($name, ['true', 'false', 'null'], true)) {
            return Term::constant(match ($name) {
                'true' => true, 'false' => false, 'null' => null
            });
        }
        $body = $this->context->index->program->constant($instruction->name);
        if ($body !== null) {
            return $this->returns(new Scope\Frame(new BodyIndex($body), 'constant:' . $instruction->name), $depth);
        }
        return (new Language\Constants($this))->value($frame, $instruction, $depth);
    }
}
