<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Derives requested definitions without constructing or advancing execution state.
 * @visibility root
 */
final class Derivation
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Expands the requested dependency and retains unresolved children.
     */
    public function value(Frame $frame, string $register, int $depth): Term
    {
        $this->context->frames[$frame->identity] = $frame;
        $instruction = $frame->graph->definitions[$register] ?? null;
        if ($instruction === null) {
            return $register === '' ? Term::constant(null) : $this->context->reference($frame, $register, $frame->graph->body->source, reason: 'MISSING_DEFINITION');
        }
        if ($instruction->operation === 'constant') {
            return $instruction->constant ?? Term::constant(null);
        }
        $key = $this->context->cacheNamespace . ':' . $frame->identity . ':' . $register . ':depth:' . $depth;
        $cached = $this->context->values[$key] ?? $this->context->cache->get($key);
        if ($cached !== null) {
            $this->context->sharedNodeHits++;
            $this->context->record($frame, $instruction);
            return $cached;
        }
        if (isset($this->context->active[$key])) {
            return $this->context->reference($frame, $register, $instruction->source, reason: 'CYCLE', kind: 'recursive');
        }
        $this->context->active[$key] = true;
        $this->context->record($frame, $instruction);
        $value = $this->instruction($frame, $instruction, $depth);
        unset($this->context->active[$key]);
        $this->context->values[$key] = $value;
        if ($this->context->stopReason === null) {
            $this->context->cache->put($key, $value);
        }
        return $value;
    }

    /**
     * Dispatches one demanded definition without advancing program state.
     */
    public function instruction(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $op = $instruction->operation;
        if ($op === 'closure') {
            return new Term('closure', $instruction->name, attributes: ['context' => $frame->identity, 'creation' => $instruction->result]);
        }
        if ($op === 'copy' || $op === 'write') {
            return $this->value($frame, $instruction->operands[$op === 'write' ? 1 : 0], $depth);
        }
        if ($op === 'read' || $op === 'read-silent') {
            return (new Storage($this))->read($frame, $instruction, $instruction->operands[0], $depth);
        }
        if ($op === 'argument') {
            return ($instruction->attributes['address'] ?? false) === true ? (new Storage($this))->read($frame, $instruction, $instruction->operands[1], $depth) : $this->value($frame, $instruction->operands[1], $depth);
        }
        if (in_array($op, ['invoke', 'invoke-method', 'invoke-static', 'new'], true)) {
            return (new Calls($this))->value($frame, $instruction, $depth);
        }
        if ($op === 'phi') {
            return $this->phi($frame, $instruction, $depth);
        }
        if ($op === 'array-set' || $op === 'array-unpack') {
            return (new ArrayConstruction($this))->value($frame, $instruction, $depth);
        }
        if ($op === 'static-initialized') {
            $local = $frame->graph->definitions[$instruction->operands[0]];
            return Term::constant(isset($this->context->configuration->environment['static:' . $frame->graph->body->symbol . ':' . $local->name]));
        }
        if ($op === 'constant-fetch') {
            return $this->constant($frame, $instruction, $depth);
        }
        $values = array_map(fn (string $operand): Term => $this->value($frame, $operand, $depth), $instruction->operands);
        $value = $this->operation($op, $instruction->name, $values);
        return $value->kind === 'operation' ? new Term($value->kind, $value->literal, $value->operands, [...$value->attributes, 'source' => $instruction->source->path, 'start' => $instruction->source->start, 'end' => $instruction->source->end]) : $value;
    }

    /**
     * Collects return expressions and their value-selecting conditions.
     */
    public function returns(Frame $frame, int $depth): Term
    {
        $alternatives = [];
        foreach ($frame->graph->returns() as [$block, $register]) {
            $conditions = (new Guards($this))->at($frame, $block, Term::constant(true), $depth);
            if ($conditions->kind === 'choice' && $conditions->operands === []) {
                continue;
            }
            $value = $this->value($frame, $register, $depth);
            $value = $frame->graph->body->blocks[$block]->terminator->kind === 'throw' ? new Term('throwable', (string) ($value->attributes['type'] ?? 'Throwable'), [$value]) : (new Language\DeclarationCoercion())->check($this, $value, $frame->graph->body->returnType, $frame->graph->body->strict, $frame->graph->body->className);
            $value = (new Language\Catches())->resolve($this, $frame, $block, $value, $depth);
            $alternatives[] = [(new Guards($this))->at($frame, $block, $value, $depth), []];
        }
        return (new Choices())->make($alternatives);
    }

    /**
     * Selects expression alternatives using the corresponding condition.
     */
    public function phi(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $condition = $this->value($frame, $instruction->operands[2], $depth);
        $alternatives = [];
        foreach ((new Choices())->alternatives($condition) as [$test, $guard]) {
            $truth = (new Operations())->truth($test);
            foreach ([true, false] as $expected) {
                if ($truth !== null && $truth !== $expected) {
                    continue;
                }
                $value = $this->value($frame, $instruction->operands[$expected ? 0 : 1], $depth);
                $guard[$frame->identity . ':condition:' . $instruction->operands[2]] = $expected;
                $alternatives[] = [$value, $guard];
            }
        }
        return (new Choices())->make($alternatives);
    }

    /**

     * @param list<Term> $values

     */
    public function operation(string $operation, string $name, array $values): Term
    {
        return (new Choices())->apply($operation . ':' . $name, $values, fn (array $operands): Term => $this->evaluate($operation, $name, $operands), $this->context->budget->partitions);
    }

    /**

     * @param list<Term> $values

     */
    public function evaluate(string $operation, string $name, array $values): Term
    {
        foreach ($values as $value) {
            if ($value->kind === 'throwable') {
                return $value;
            }
        }
        $operations = new Operations($this->context->configuration->target->floatPrecision);
        return match ($operation) {
            'binary' => $operations->binary($name, $values[0], $values[1]),
            'unary' => $operations->unary($name, $values[0]),
            'cast' => $operations->cast($name, $values[0]),
            'not-null' => $values[0]->kind === 'constant' ? Term::constant($values[0]->literal !== null) : new Term('operation', 'not-null', $values),
            'array-read' => $this->element($values[0], $values[1]),
            'array-set' => in_array($values[1]->kind, ['constant', 'append'], true) ? (new \Deriver\Value\Arrays())->set($values[0], $values[1]->kind === 'append' ? null : $values[1], $values[2]) : new Term('array-set', operands: $values, attributes: ['type' => 'array']),
            'intrinsic' => $this->intrinsic($name, $values),
            'throw' => new Term('throwable', 'explicit-throw', $values),
            'external', 'external-body' => new Term('operation', $name !== '' ? $name : $operation, $values, ['reason' => 'EXTERNAL_INPUT']),
            default => new Term('operation', $operation . ':' . $name, $values, ['reason' => 'UNSUPPORTED_OPERATION']),
        };
    }

    /**
     * Reads a known array key or retains the indexing expression.
     */
    public function element(Term $array, Term $key): Term
    {
        $key = (new Operations())->arrayKey($key);
        if ($array->kind === 'array' && $key->kind === 'constant' && (is_int($key->literal) || is_string($key->literal))) {
            return $array->operands[$key->literal] ?? Term::constant(null);
        }
        return new Term('array-read', operands: [$array, $key]);
    }

    /**

     * @param list<Term> $values

     */
    public function intrinsic(string $name, array $values): Term
    {
        $intrinsic = $this->context->models->extensions->intrinsics[$name] ?? null;
        $result = $intrinsic === null ? (new ScalarFunctions($this->context->configuration->target->floatPrecision))->apply($name, $values) : (new \Deriver\Model\Intrinsic\IntrinsicEvaluation())->evaluate($intrinsic, $values, $this->context->configuration->target);
        return $result->kind === 'opaque' ? new Term('operation', 'intrinsic:' . $name, $values, ['reason' => in_array($name, ['time', 'getenv', 'random_int', 'rand', 'mt_rand', 'microtime'], true) ? 'EXTERNAL_INPUT' : 'UNSUPPORTED_OPERATION']) : $result;
    }

    /**
     * Resolves captured constant definitions without executing application code.
     */
    public function constant(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $name = strtolower($instruction->name);
        if (in_array($name, ['true', 'false', 'null'], true)) {
            return Term::constant(match ($name) {
                'true' => true, 'false' => false, 'null' => null
            });
        }
        $body = $this->context->index->program->constant($instruction->name);
        return $body === null ? $this->context->reference($frame, $instruction->name, $instruction->source, reason: 'MISSING_CONSTANT') : $this->returns(new Frame(new Graph($body), 'constant:' . $instruction->name), $depth);
    }
}
