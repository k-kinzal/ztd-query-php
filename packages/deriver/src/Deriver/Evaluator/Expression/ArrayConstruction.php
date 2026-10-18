<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Evaluates ordered aggregate operands once without retaining every intermediate array.
 * @visibility parent
 */
final class ArrayConstruction
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly \Deriver\Deriver\Evaluator\Evaluator $engine)
    {
    }

    /**
     * Expands the requested dependency and retains unresolved children.
     */
    public function value(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $instruction, int $depth): Term
    {
        $writes = $this->chain($frame, $instruction);
        if ($writes instanceof Term) {
            return $writes;
        }
        $current = array_pop($writes);
        $base = $this->engine->value($frame, $current->result, $depth);
        $entries = $base->kind === \Deriver\Deriver\Evaluator\TermKind::Array ? $base->operands : [];
        $next = $base->kind === \Deriver\Deriver\Evaluator\TermKind::Array ? (new \Deriver\Deriver\Evaluator\Scalar\Arrays())->next($base) : 0;
        $integer = array_filter(array_keys($entries), is_int(...)) !== [];
        $residual = $base->kind === \Deriver\Deriver\Evaluator\TermKind::Array ? null : $base;
        foreach (array_reverse($writes) as $write) {
            if (($reason = $this->engine->context->work()) !== null) {
                return new Term(\Deriver\Deriver\Evaluator\TermKind::ArrayContinuation, operands: [$residual ?? Term::array($entries), $this->engine->context->reference($frame, $write->result, $write->source, reason: $reason)], attributes: ['type' => 'array', 'reason' => $reason]);
            }
            $key = $write->operands[1] === '' ? new Term(\Deriver\Deriver\Evaluator\TermKind::Append) : $this->engine->value($frame, $write->operands[1], $depth);
            $value = $this->engine->value($frame, $write->operands[2], $depth);
            if ($write->operation === 'array-unpack' && $value->kind === \Deriver\Deriver\Evaluator\TermKind::Array && ($value->attributes['open'] ?? false) === false) {
                foreach ($value->operands as $offset => $entry) {
                    $this->append($entries, $next, $integer, $residual, is_int($offset) ? new Term(\Deriver\Deriver\Evaluator\TermKind::Append) : Term::constant($offset), $entry);
                }
            } elseif ($write->operation === 'array-unpack') {
                $residual = new Term(\Deriver\Deriver\Evaluator\TermKind::ArrayUnpack, operands: [$residual ?? new Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $entries), $value], attributes: ['type' => 'array']);
            } else {
                $this->append($entries, $next, $integer, $residual, $key, $value);
            }
        }
        return $residual ?? $this->choices(new Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $entries, attributes: ['open' => false, 'next' => $next]));
    }

    /**
     * Opens only choosing entries, retaining shared fixed entries without repeated copying.
     */
    public function choices(Term $array): Term
    {
        $selected = array_filter($array->operands, static fn (Term $value): bool => $value->kind === \Deriver\Deriver\Evaluator\TermKind::Choice);
        if ($selected === []) {
            return $array;
        }
        $keys = array_keys($selected);
        $result = (new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->apply('array', array_values($selected), static fn (array $values): Term => new Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: array_replace($array->operands, array_combine($keys, $values)), attributes: $array->attributes), $this->engine->context->budget->partitions);
        return $result->kind === \Deriver\Deriver\Evaluator\TermKind::Operation ? new Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $array->operands, attributes: [...$array->attributes, 'reason' => 'ENUMERATION_LIMIT']) : $result;
    }

    /**
     * Collects the ordered aggregate spine once, leaving the base as its last item.
     * @return non-empty-list<Instruction>|Term Construction spine or interrupted expression
     */
    public function chain(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $instruction): array|Term
    {
        $writes = [];
        $current = $instruction;
        while (in_array($current->operation, ['array-set', 'array-unpack'], true)) {
            if (($reason = $this->engine->context->work()) !== null) {
                return (new \Deriver\Deriver\Evaluator\Enumeration\Suspension())->expression($this->engine->context, $frame, $instruction, $reason);
            }
            $writes[] = $current;
            $previous = $frame->graph->definitions[$current->operands[0]] ?? null;
            if ($previous === null) {
                break;
            }
            $current = $previous;
        }
        $writes[] = $current;
        return $writes;
    }

    /**
     * Builds concrete entries in one buffer and retains symbolic ordered updates.
     * @param array<int|string, Term> $entries Current concrete prefix
     */
    public function append(array &$entries, ?int &$next, bool &$integer, ?Term &$residual, Term $key, Term $value): void
    {
        if ($residual?->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
            return;
        }
        if ($residual !== null || !in_array($key->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Append], true)) {
            $residual ??= new Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $entries, attributes: ['open' => false, 'next' => $next]);
            $residual = new Term(\Deriver\Deriver\Evaluator\TermKind::ArraySet, operands: [$residual, $key, $value], attributes: ['type' => 'array']);
            return;
        }
        $normalized = $key->kind === \Deriver\Deriver\Evaluator\TermKind::Append ? Term::constant($next) : (new Operations())->arrayKey($key);
        if ($normalized->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable || !is_int($normalized->literal) && !is_string($normalized->literal)) {
            $residual = $normalized;
            return;
        }
        $raw = $normalized->literal;
        if ($key->kind === \Deriver\Deriver\Evaluator\TermKind::Append && $raw === PHP_INT_MAX && isset($entries[$raw])) {
            $residual = new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error');
            return;
        }
        $entries[$raw] = $value;
        if (is_int($raw)) {
            $after = $raw === PHP_INT_MAX ? $raw : $raw + 1;
            $next = $integer ? max($next, $after) : $after;
            $integer = true;
        }
    }
}
