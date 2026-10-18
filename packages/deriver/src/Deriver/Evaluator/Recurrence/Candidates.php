<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use WeakMap;

/**
 * Derives structural entry and update candidates with optional bound constraints.
 * @visibility parent
 */
final class Candidates
{
    /**
     * Shares demanded storage and evidence with ordinary value derivation.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Resolves symbolic inputs or starts a recurrence; explicit versions remain with Definitions.
     */
    public function resolve(Frame $frame, string $address, int $header, int $depth): ?Term
    {
        $group = ($frame->baseIdentity ?? $frame->identity) . ':' . $header;
        $key = $group . ':' . (new Storage($this->engine))->key($frame, $address);
        if (($frame->iterations[$header] ?? 0) === 0) {
            if (isset($this->engine->context->loopInputs[$key])) {
                return $this->engine->context->loopInputs[$key];
            }
            $initial = $this->engine->context->loopFrames[$group] ?? null;
            if ($initial !== null) {
                return $this->update($frame, $initial, $address, $header, $depth);
            }
        }
        return isset($frame->iterations[$header]) ? null : $this->value($frame, $address, $header, $depth);
    }

    /**
     * Keeps independent writes as alternatives and dependent updates as a recurrence.
     */
    public function value(Frame $frame, string $address, int $header, int $depth): Term
    {
        $identity = $frame->identity . ':recurrence:' . $header;
        $symbolic = new Frame($frame->graph, $identity, $frame->bindings, $frame->properties, $frame->calls, $frame->invocation, $frame->iterations, $identity, $frame->origin, $frame->calledClass);
        $this->engine->context->loopFrames[$identity . ':' . $header] = $frame;
        return $this->update($symbolic, $frame, $address, $header, $depth);
    }

    /**
     * Shares symbolic inputs across the storage locations needed by one recurrence.
     */
    public function update(Frame $symbolic, Frame $entry, string $address, int $header, int $depth): Term
    {
        $definitions = new Definitions($this->engine);
        $bounds = (new Bounds($this->engine))->of($entry, $header, $depth);
        if (($bounds->attributes['maximum'] ?? -1) === 1) {
            $version = $definitions->postCondition($entry, $header) ? 0 : 1;
            return $this->engine->context->provenance->operation($definitions->version($entry->iteration($header, $version), $address, $header, $version, $depth), [$bounds], 'loop-once');
        }
        $initial = $definitions->version($entry->iteration($header, 0), $address, $header, 0, $depth);
        if (($bounds->attributes['maximum'] ?? -1) === 0) {
            return $this->engine->context->provenance->operation($initial, [$bounds], 'loop-empty');
        }
        if ((new Invariant())->check($this->engine, $entry, $address, $header)) {
            return $initial;
        }
        $storage = (new Storage($this->engine))->key($symbolic, $address);
        $reference = $this->engine->context->reference($symbolic, $storage, $symbolic->graph->body->source, reason: 'LOOP_DEPENDENCY', kind: TermKind::Recursive);
        $key = ($symbolic->baseIdentity ?? $symbolic->identity) . ':' . $header . ':' . $storage;
        $this->engine->context->loopInputs[$key] = $reference;
        $update = $definitions->version($symbolic->iteration($header, 1), $address, $header, 1, $depth);
        $update = (new Choices($this->engine->context->provenance))->map($update, fn (Term $leaf): Term => $leaf->kind === TermKind::Recursive && ($leaf->attributes['identity'] ?? null) === $reference->attributes['identity'] ? $initial : $leaf);
        $value = $this->dependsOn($update, $reference)
            ? new Term(TermKind::Recurrence, operands: [$initial, $update, $reference], attributes: ['reason' => 'LOOP_DEPENDENCY', 'header' => $header])
            : (new Choices($this->engine->context->provenance))->make(($bounds->attributes['minimum'] ?? 0) === 1 ? [[$update, []]] : [[$initial, []], [$update, []]]);
        if (($bounds->attributes['minimum'] ?? 0) === 1) {
            $value = $this->engine->context->provenance->operation($value, [$bounds], 'loop-entered');
        }
        return $this->engine->context->loopInputs[$key] = $value;
    }

    /**
     * Recognizes a retained loop input even after evidence has copied its value.
     */
    public function dependsOn(Term $value, Term $reference): bool
    {
        $pending = [$value];
        $seen = new WeakMap();
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            if ($node->kind === TermKind::Recursive && ($node->attributes['identity'] ?? null) === $reference->attributes['identity']) {
                return true;
            }
            array_push($pending, ...array_values($node->operands));
        }
        return false;
    }
}
