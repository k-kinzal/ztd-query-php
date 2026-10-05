<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Evaluates ordered aggregate operands once without retaining every intermediate array.
 * @visibility root
 */
final class ArrayConstruction
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**
     * Expands the requested dependency and retains unresolved children.
     */
    public function value(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $writes = $this->chain($frame, $instruction);
        if ($writes instanceof Term) {
            return $writes;
        }
        $current = array_pop($writes);
        $base = $this->engine->value($frame, $current->result, $depth);
        $entries = $base->kind === 'array' ? $base->operands : [];
        $next = $base->kind === 'array' ? (new \Deriver\Value\Arrays())->next($base) : 0;
        $integer = array_filter(array_keys($entries), is_int(...)) !== [];
        $secret = $base->isSecret();
        $residual = $base->kind === 'array' ? null : $base;
        foreach (array_reverse($writes) as $write) {
            if (($reason = $this->engine->context->work()) !== null) {
                return new Term('array-continuation', operands: [$residual ?? Term::array($entries), $this->engine->context->reference($frame, $write->result, $write->source, reason: $reason)], attributes: ['type' => 'array', 'reason' => $reason]);
            }
            $key = $write->operands[1] === '' ? new Term('append') : $this->engine->value($frame, $write->operands[1], $depth);
            $value = $this->engine->value($frame, $write->operands[2], $depth);
            $secret = $secret || $key->isSecret() || $value->isSecret();
            if ($write->operation === 'array-unpack' && $value->kind === 'array' && ($value->attributes['open'] ?? false) === false) {
                foreach ($value->operands as $offset => $entry) {
                    $this->append($entries, $next, $integer, $residual, is_int($offset) ? new Term('append') : Term::constant($offset), $entry);
                }
            } elseif ($write->operation === 'array-unpack') {
                $residual = new Term('array-unpack', operands: [$residual ?? new Term('array', operands: $entries), $value], attributes: ['type' => 'array']);
            } else {
                $this->append($entries, $next, $integer, $residual, $key, $value);
            }
        }
        return $residual ?? $this->choices(new Term('array', operands: $entries, attributes: ['open' => false, 'next' => $next], secret: $secret));
    }

    /**
     * Opens only choosing entries, retaining shared fixed entries without repeated copying.
     */
    public function choices(Term $array): Term
    {
        $selected = array_filter($array->operands, static fn (Term $value): bool => $value->kind === 'choice');
        if ($selected === []) {
            return $array;
        }
        $keys = array_keys($selected);
        $result = (new Choices())->apply('array', array_values($selected), static fn (array $values): Term => new Term('array', operands: array_replace($array->operands, array_combine($keys, $values)), attributes: $array->attributes, secret: $array->isSecret()), $this->engine->context->budget->partitions);
        return $result->kind === 'operation' ? new Term('array', operands: $array->operands, attributes: [...$array->attributes, 'reason' => 'ENUMERATION_LIMIT'], secret: $array->isSecret()) : $result;
    }

    /**
     * Collects the ordered aggregate spine once, leaving the base as its last item.
     * @return non-empty-list<Instruction>|Term Construction spine or interrupted expression
     */
    public function chain(Frame $frame, Instruction $instruction): array|Term
    {
        $writes = [];
        $current = $instruction;
        while (in_array($current->operation, ['array-set', 'array-unpack'], true)) {
            if (($reason = $this->engine->context->work()) !== null) {
                return (new Enumeration\Suspension())->expression($this->engine->context, $frame, $instruction, $reason);
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
        if ($residual?->kind === 'throwable') {
            return;
        }
        if ($residual !== null || !in_array($key->kind, ['constant', 'append'], true)) {
            $residual ??= new Term('array', operands: $entries, attributes: ['open' => false, 'next' => $next]);
            $residual = new Term('array-set', operands: [$residual, $key, $value], attributes: ['type' => 'array']);
            return;
        }
        $normalized = $key->kind === 'append' ? Term::constant($next) : (new Operations())->arrayKey($key);
        if ($normalized->kind === 'throwable' || !is_int($normalized->literal) && !is_string($normalized->literal)) {
            $residual = $normalized;
            return;
        }
        $raw = $normalized->literal;
        if ($key->kind === 'append' && $raw === PHP_INT_MAX && isset($entries[$raw])) {
            $residual = new Term('throwable', 'Error');
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
