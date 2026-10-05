<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Recurrence;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Storage;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Evaluates demanded loop-carried definitions by version, without executing unrelated loop instructions.
 * @visibility root
 */
final class Definitions
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
    public function value(Frame $frame, string $address, int $header, int $depth): Term
    {
        if ((new Invariant())->check($this->engine, $frame, $address, $header)) {
            return $this->version($frame->iteration($header, 0), $address, $header, 0, $depth);
        }
        if (isset($frame->iterations[$header])) {
            return $this->version($frame, $address, $header, $frame->iterations[$header], $depth);
        }
        $condition = $frame->graph->body->blocks[$header]->terminator->operand;
        $iteration = 0;
        for (; $iteration < $this->engine->context->budget->iterations; $iteration++) {
            $version = $frame->iteration($header, $iteration);
            $test = $this->engine->value($version, $condition, $depth);
            $truths = array_map(static fn (array $alternative): ?bool => (new Operations())->truth($alternative[0]), iterator_to_array((new Choices())->alternatives($test), false));
            if ($truths !== [] && array_unique($truths, SORT_REGULAR) === [false]) {
                return $this->version($version, $address, $header, $iteration, $depth);
            }
            if ($truths === [] || in_array(null, $truths, true) || in_array(false, $truths, true) || $this->engine->context->boundary($depth) !== null) {
                break;
            }
        }
        $initial = $this->version($frame->iteration($header, 0), $address, $header, 0, $depth);
        $reason = $this->engine->context->stopReason ?? ($iteration >= $this->engine->context->budget->iterations ? 'ITERATION_LIMIT' : 'UNKNOWN_ITERATION_COUNT');
        $reference = $this->engine->context->reference($frame, (new Storage($this->engine))->key($frame, $address), $frame->graph->body->source, reason: $reason, kind: 'recursive');
        $update = $this->version($frame->iteration($header, 1), $address, $header, 1, $depth);
        return new Term('recurrence', operands: [$initial, $update, $reference], attributes: ['reason' => $reason, 'header' => $header]);
    }

    /**
     * Resolves one demanded recurrence version from its predecessor.
     */
    public function version(Frame $frame, string $address, int $header, int $iteration, int $depth): Term
    {
        $key = $frame->identity . ':loop-value:' . $address . ':' . $header . ':' . $iteration . ':' . $depth;
        if (isset($this->engine->context->values[$key])) {
            return $this->engine->context->values[$key];
        }
        $alternatives = [];
        foreach ($frame->graph->predecessors[$header] ?? [] as $parent) {
            $backedge = $parent >= $header;
            $post = $this->postCondition($frame, $header);
            if (!$post && ($iteration === 0) === $backedge) {
                continue;
            }
            $source = $backedge && !$post ? $frame->iteration($header, $iteration - 1) : $frame;
            $value = (new Storage($this->engine))->search($source, $address, $parent, count($source->graph->body->blocks[$parent]->instructions), $depth);
            $alternatives[] = [$value, []];
        }
        return $this->engine->context->values[$key] = (new Choices())->make($alternatives);
    }
    /**
     * Recognizes a loop whose first body precedes its first condition.
     */
    public function postCondition(Frame $frame, int $header): bool
    {
        foreach ($frame->graph->predecessors[$header] ?? [] as $parent) {
            if ($parent < $header) {
                return false;
            }
        }
        return true;
    }

    /**
     * Selects the entry or backedge origin of a post-condition body version.
     * @return list<int> Relevant predecessor blocks
     */
    public function parents(Frame $frame, int $block): array
    {
        $parents = $frame->graph->predecessors[$block] ?? [];
        foreach ($frame->iterations as $header => $version) {
            if ($frame->graph->body->blocks[$header]->terminator->targets[0] === $block && $this->postCondition($frame, $header)) {
                return array_values(array_filter($parents, static fn (int $parent): bool => $version === 0 ? $parent !== $header : $parent === $header));
            }
        }
        return $parents;
    }

    /**
     * Selects the previous condition version when reading a do-loop body input.
     */
    public function predecessor(Frame $frame, int $parent, int $child): Frame
    {
        $version = $frame->iterations[$parent] ?? null;
        return $version !== null && $version > 0 && $this->postCondition($frame, $parent) && $frame->graph->body->blocks[$parent]->terminator->targets[0] === $child ? $frame->iteration($parent, $version - 1) : $frame;
    }
}
