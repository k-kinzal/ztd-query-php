<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scope;

/**
 * An actual argument or capture retained without requesting its value.
 * @visibility parent
 */
final class Binding
{
    /**
     * @param list<string>|null $registers Lazy variadic arguments
     * @param array{int, int}|null $position Capture position for a storage binding
     */
    public function __construct(public readonly Frame $frame, public readonly string $register, public readonly ?array $registers = null, public readonly ?array $position = null)
    {
    }
    /**
     * Propagates boolean demand through uncoerced lazy actual arguments.
     */
    public function truth(\Deriver\Deriver\Evaluator\Evaluator $engine, string $type, int $depth, int $wanted = 3): \Deriver\Deriver\Evaluator\Term
    {
        if ($this->registers === null) {
            if (in_array($type, ['', 'mixed'], true)) {
                return $this->position === null
                    ? (new \Deriver\Deriver\Evaluator\Predicate\Truth($engine, $wanted))->value($this->frame, $this->register, $depth)
                    : (new \Deriver\Deriver\Evaluator\Expression\Storage($engine, true, $wanted))->search($this->frame, $this->register, $this->position[0], $this->position[1], $depth);
            }
            return (new \Deriver\Deriver\Evaluator\Predicate\Projection($engine->context->provenance))->value($this->finite($engine, $type, $depth, \Deriver\Deriver\Evaluator\Enumeration\Finite\Demand::truth($wanted)));
        }
        return (new \Deriver\Deriver\Evaluator\Predicate\Projection($engine->context->provenance))->value($this->value($engine, $type, $depth));
    }

    /**
     * Resolves lazy inputs until the consumer's finite observation is covered.
     */
    public function finite(\Deriver\Deriver\Evaluator\Evaluator $engine, string $type, int $depth, \Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite): \Deriver\Deriver\Evaluator\Term
    {
        if ($this->registers !== null) {
            return $this->value($engine, $type, $depth);
        }
        $demand = $finite->through($engine, $type, $this->frame->graph->body->strict);
        $value = $this->position === null
            ? (new \Deriver\Deriver\Evaluator\Predicate\Truth($engine, 3, $demand))->value($this->frame, $this->register, $depth)
            : (new \Deriver\Deriver\Evaluator\Expression\Storage($engine, true, 3, $demand))->search($this->frame, $this->register, $this->position[0], $this->position[1], $depth);
        return (new \Deriver\Deriver\Evaluator\Language\DeclarationCoercion())->check($engine, $value, $type, $this->frame->graph->body->strict);
    }

    /**
     * Resolves only this actual argument or lexical storage capture.
     */
    public function value(\Deriver\Deriver\Evaluator\Evaluator $engine, string $type, int $depth): \Deriver\Deriver\Evaluator\Term
    {
        if ($this->position !== null) {
            return (new \Deriver\Deriver\Evaluator\Expression\Storage($engine))->search($this->frame, $this->register, $this->position[0], $this->position[1], $depth);
        }
        if ($this->registers !== null) {
            return \Deriver\Deriver\Evaluator\Term::array(array_map(fn (string $register): \Deriver\Deriver\Evaluator\Term => $engine->value($this->frame, $register, $depth), $this->registers));
        }
        $value = $engine->value($this->frame, $this->register, $depth);
        return (new \Deriver\Deriver\Evaluator\Language\DeclarationCoercion())->check($engine, $value, $type, $this->frame->graph->body->strict);
    }
}
