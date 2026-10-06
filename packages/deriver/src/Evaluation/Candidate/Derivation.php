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
            $this->context->record($frame, $instruction);
            $value = (new Invocation\Rules($this))->apply($frame, $instruction, $depth) ?? $instruction->constant ?? Term::constant(null);
            $value = Evidence\Provenance::wrap($value, 'source-definition', $instruction->source, ['owner' => $frame->graph->body->symbol, 'definition' => $register, 'operation' => 'constant']);
            return $this->bounded($frame, $instruction, $value);
        }
        $key = $this->context->cacheNamespace . ':' . $frame->identity . ':' . $register . ':depth:' . $depth;
        $cached = $this->context->values[$key] ?? $this->context->cache->get($key);
        if ($cached !== null) {
            $this->context->sharedNodeHits++;
            $this->context->record($frame, $instruction);
            return $this->bounded($frame, $instruction, $cached);
        }
        if (isset($this->context->active[$key])) {
            return $this->context->reference($frame, $register, $instruction->source, reason: 'CYCLE', kind: 'recursive');
        }
        $this->context->active[$key] = true;
        $this->context->record($frame, $instruction);
        $value = $this->instruction($frame, $instruction, $depth);
        $value = Evidence\Provenance::wrap($value, in_array($instruction->operation, ['read', 'read-silent', 'argument'], true) ? 'reference-expansion' : 'operation', $instruction->source, ['owner' => $frame->graph->body->symbol, 'definition' => $register, 'operation' => $instruction->operation, 'context' => $frame->identity]);
        $value = $this->bounded($frame, $instruction, $value);
        unset($this->context->active[$key]);
        $this->context->values[$key] = $value;
        if ($this->context->stopReason === null) {
            $this->context->cache->put($key, $value);
        }
        return $value;
    }

    /**
     * Keeps stopping reasons in the evidence and charges cached and literal proofs too.
     */
    public function bounded(Frame $frame, Instruction $instruction, Term $value): Term
    {
        if (isset($value->attributes['reason']) && $value->evidence?->kind !== 'unexpanded') {
            $value = Evidence\Provenance::wrap($value, 'unexpanded', $instruction->source, ['symbol' => (string) $value->literal, 'owner' => $frame->graph->body->symbol, 'context' => $frame->identity, 'reason' => $value->attributes['reason']]);
        }
        return $this->context->acceptEvidence($value) ? $value : (new Enumeration\Suspension())->expression($this->context, $frame, $instruction, 'EVIDENCE_LIMIT');
    }

    /**
     * Dispatches one demanded definition without advancing program state.
     */
    public function instruction(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $op = $instruction->operation;
        if (($reason = $this->context->work()) !== null) {
            return (new Enumeration\Suspension())->expression($this->context, $frame, $instruction, $reason);
        }
        $override = (new Invocation\Rules($this))->apply($frame, $instruction, $depth);
        if ($override !== null) {
            return $override;
        }
        return match ($op) {
            'closure' => new Term('closure', $instruction->name, attributes: ['context' => $frame->identity, 'creation' => $instruction->result]),
            'copy', 'write' => $this->value($frame, $instruction->operands[$op === 'write' ? 1 : 0], $depth),
            'compound', 'increment' => Memory\Mutations::value($this, $frame, $instruction, $depth),
            'read', 'read-silent' => (new Storage($this))->read($frame, $instruction, $instruction->operands[0], $depth),
            'argument' => ($instruction->attributes['address'] ?? false) === true ? (new Storage($this))->read($frame, $instruction, $instruction->operands[1], $depth) : $this->value($frame, $instruction->operands[1], $depth),
            'invoke', 'invoke-method', 'invoke-static', 'new' => (new Calls($this))->value($frame, $instruction, $depth),
            'phi' => $this->phi($frame, $instruction, $depth),
            'array-set', 'array-unpack' => (new ArrayConstruction($this))->value($frame, $instruction, $depth),
            'static-initialized' => (new Memory\Statics())->initialized($this, $frame, $frame->graph->definitions[$instruction->operands[0]], $depth),
            'constant-fetch' => $this->constant($frame, $instruction, $depth),
            'class-constant' => (new Language\Expressions($this))->constant($frame, $instruction, $depth),
            'clone' => (new Language\Expressions($this))->copy($frame, $instruction, $depth),
            'iterator', 'iterate', 'iterator-value', 'iterator-key', 'iterator-address' => (new Recurrence\Iteration())->value($this, $frame, $instruction, $depth),
            default => $this->expression($frame, $instruction, $depth),
        };
    }

    /**
     * Expands ordinary expression operands through the same candidate machinery.
     */
    public function expression(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $op = $instruction->operation;
        $values = array_map(fn (string $operand): Term => $this->value($frame, $operand, $depth), $instruction->operands);
        if ($op === 'intrinsic') {
            $callback = (new Language\Callbacks())->apply($this, $frame, $instruction, $values, $depth);
            if ($callback !== null) {
                return Evidence\Provenance::operation($callback, $values, $instruction->name);
            }
        }
        if ($op === 'cast' && strtolower($instruction->name) === 'string' || $op === 'binary' && $instruction->name === '.') {
            $values = array_map(fn (Term $value): Term => (new Language\Expressions($this))->string($frame, $instruction, $value, $depth), $values);
        }
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
            $value = (new Language\Finalizers())->apply($this, $frame, $block, $value, $depth);
            $alternatives[] = [(new Guards($this))->at($frame, $block, $value, $depth), []];
        }
        return Evidence\Provenance::model((new Choices())->make($alternatives), $frame->graph->modelEvidence);
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
                $value = Evidence\Provenance::operation($value, [$test], 'selection');
                $value = Evidence\Provenance::wrap($value, 'choice', $instruction->source, ['selector' => $frame->identity . ':condition:' . $instruction->operands[2], 'branch' => $expected]);
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
            'throw' => new Term('throwable', (string) ($values[0]->attributes['type'] ?? 'Throwable'), $values),
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
        if ($array->kind === 'constant' && is_string($array->literal) && $key->kind === 'constant' && is_int($key->literal)) {
            return isset($array->literal[$key->literal]) ? Term::constant($array->literal[$key->literal], $array->isSecret() || $key->isSecret()) : new Term('array-read', operands: [$array, $key], attributes: ['reason' => 'MISSING_OFFSET']);
        }
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
        if ($body !== null) {
            return $this->returns(new Frame(new Graph($body), 'constant:' . $instruction->name), $depth);
        }
        return (new Language\Constants($this))->value($frame, $instruction, $depth);
    }
}
