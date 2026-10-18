<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Scalar\Increment;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Describes storage mutations for both origin discovery and reaching definitions.
 * @visibility parent
 */
final class Mutations
{
    /**
     * Evaluates a selected reaching write after storage identity has been resolved.
     * @param array<string, true> $seen Previously visited storage definitions
     */
    public static function apply(Evaluator $engine, Frame $frame, Instruction $write, string $address, int $block, int $offset, int $depth, array $seen, bool $truth = false, int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null): Term
    {
        $engine->context->record($frame, $write);
        if ($write->operation === 'write') {
            if ($truth && ($frame->graph->definitions[$address]->operation ?? '') === 'local') {
                return $engine->context->provenance->wrap((new \Deriver\Deriver\Evaluator\Predicate\Truth($engine, $wanted, $finite))->value($frame, $write->operands[1], $depth), \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::StorageWrite, $write->source, ['owner' => $frame->graph->body->symbol, 'storage' => (new Aliases())->key(new Storage($engine), $frame, $address, $block, $offset), 'version' => $write->result, 'context' => $frame->identity]);
            }
            $hook = (new PropertyHooks())->write($engine, $frame, $write, $address, $block, $offset, $depth);
            if ($hook !== null) {
                return $hook;
            }
            return $engine->context->provenance->wrap($engine->value($frame, $write->operands[1], $depth), \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::StorageWrite, $write->source, ['owner' => $frame->graph->body->symbol, 'storage' => (new Aliases())->key(new Storage($engine), $frame, $address, $block, $offset), 'version' => $write->result, 'context' => $frame->identity]);
        }
        if ($write->operation === 'unset') {
            return Term::constant(null);
        }
        $before = (new Storage($engine))->search($frame, $address, $block, $offset, $depth, $seen);
        if ($write->operation === 'increment') {
            return Mutations::increment($engine, $write, $before);
        }
        return $engine->operation('binary', $write->name, [$before, $engine->value($frame, $write->operands[1], $depth)]);
    }

    /**
     * Reads the expression result at the mutation's before or after position.
     */
    public static function value(Evaluator $engine, Frame $frame, Instruction $write, int $depth): Term
    {
        [$block, $offset] = $frame->graph->positions[$write->result];
        $after = $write->operation !== 'increment' || ($write->attributes['post'] ?? false) !== true;
        return (new Storage($engine))->search($frame, $write->operands[0], $block, $offset + ($after ? 1 : 0), $depth);
    }

    /**
     * Shares PHP target increment semantics across scalar, element and property writes.
     */
    public static function increment(Evaluator $engine, Instruction $write, Term $before): Term
    {
        return (new Choices($engine->context->provenance))->apply('increment', [$before], static function (array $values) use ($write): Term {
            $operation = new Increment();
            $delta = (int) $write->attributes['delta'];
            $value = $operation->apply($values[0], $delta);
            return $operation->warning($values[0], $delta) ? new Term($value->kind, $value->literal, $value->operands, [...$value->attributes, 'reason' => 'PHP_WARNING', 'source' => $write->source->path, 'start' => $write->source->start, 'end' => $write->source->end]) : $value;
        }, $engine->context->budget->partitions);
    }
}
