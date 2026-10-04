<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Value\Term;

/**
 * Expands formal inputs and declared property origins within the captured source world.
 * @visibility root
 */
final class Origins
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**
     * Expands a bound or caller-derived input and retains natural input boundaries.
     */
    public function parameter(Frame $frame, Instruction $local, int $depth): Term
    {
        $parameters = array_column($frame->graph->body->parameters, null, 'name');
        $parameter = $parameters[$local->name] ?? null;
        $binding = $frame->bindings[$local->name] ?? null;
        if ($binding instanceof Term) {
            return $binding;
        }
        if ($binding instanceof Binding) {
            return $binding->value($this->engine, $parameter->type ?? 'mixed', $depth);
        }
        $type = $parameter->type ?? ($local->name === 'this' ? $frame->graph->body->className : 'mixed');
        if ($frame->invocation && $parameter?->default !== null) {
            return $this->engine->returns(new Frame(new Graph($parameter->default), $frame->identity . ':default:' . $local->name), $depth);
        }
        if (array_key_exists($local->name, $frame->graph->body->captures)) {
            $captured = (new Memory\Captures())->value($this->engine, $frame, $local->name, $depth);
            if ($captured !== null) {
                return $captured;
            }
        }
        if (!$frame->invocation && $parameter !== null) {
            $key = $frame->identity . ':origin:$' . $local->name;
            if (isset($this->engine->context->active[$key])) {
                return $this->engine->context->reference($frame, '$' . $local->name, $local->source, $type, 'CYCLE', 'recursive');
            }
            $this->engine->context->active[$key] = true;
            $values = $this->callers($frame, $local->name, $depth);
            unset($this->engine->context->active[$key]);
            if ($values !== null) {
                return $values;
            }
        }
        return $this->unbound($frame, $local, $type, $parameter !== null);
    }

    /**
     * Retains a natural input boundary with its source identity and known type.
     */
    public function unbound(Frame $frame, Instruction $local, string $type, bool $parameter): Term
    {
        if (in_array($local->name, ['_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES', '_SESSION', 'GLOBALS'], true)) {
            return $this->engine->context->configuration->environment['global:' . $local->name] ?? $this->engine->context->reference($frame, 'global:' . $local->name, $local->source, 'array');
        }
        return $this->engine->context->reference($frame, '$' . $local->name, $parameter ? $frame->graph->body->source : $local->source, $type, !$parameter && $local->name !== 'this' ? 'UNRESOLVED_LOCAL' : 'EXTERNAL_INPUT');
    }

    /**
     * Finds and expands actual arguments belonging to the requested declaration.
     */
    public function callers(Frame $frame, string $name, int $depth): ?Term
    {
        $sites = $this->engine->context->index->callers($frame->graph->body->symbol);
        if ($sites === []) {
            return null;
        }
        $values = [];
        $group = $frame->identity . ':caller';
        foreach ($sites as $index => [$graph, $instruction]) {
            foreach ($this->engine->context->entryFrames[strtolower($graph->body->symbol)] ?? [new Frame($graph, 'source:' . $graph->body->symbol)] as $caller) {
                $bound = (new Calls($this->engine))->bind($caller, $instruction, $frame->graph);
                $binding = $bound->bindings[$name] ?? null;
                $local = new Instruction('input:' . $name, 'local', $instruction->source, name: $name);
                $value = $this->parameter($bound, $local, $depth);
                $guard = [];
                foreach (array_keys($sites) as $other) {
                    $guard[$group . ':' . $other] = $index === $other;
                }
                $values[] = [$value, $guard];
            }
        }
        return (new Choices())->make($values);
    }

    /**
     * Resolves the requested property and its candidate origins.
     */
    public function property(Frame $frame, Instruction $address, int $depth): Term
    {
        $index = $this->engine->context->index;
        $name = $index->literal($frame->graph, $address->operands[1]);
        if (isset($frame->properties[$name])) {
            return $frame->properties[$name];
        }
        $property = $index->declaredProperty($frame->graph, $address);
        $receiver = null;
        if ($address->operation === 'field-address' && ($frame->invocation || $property === null)) {
            $receiver = $this->engine->value($frame, $address->operands[0], $depth);
            if ($receiver->kind === 'throwable') {
                return $receiver;
            }
        }
        if ($property === null && $receiver !== null) {
            $property = $index->property((string) ($receiver->attributes['type'] ?? ''), $name);
        }
        if ($property === null) {
            return $this->engine->context->reference($frame, 'property:' . $name, $address->source, reason: 'UNRESOLVED_PROPERTY');
        }
        if ($receiver !== null) {
            $allocated = (new Memory\Properties())->allocated($this->engine, $receiver, $property, $depth);
            if ($allocated !== null) {
                return $allocated;
            }
        }
        $identity = $property->className . '::$' . $property->name;
        $key = $frame->identity . ':property:' . $identity;
        if (isset($this->engine->context->active[$key])) {
            return $this->engine->context->reference($frame, $identity, $address->source, $property->type, 'CYCLE', 'recursive');
        }
        $this->engine->context->active[$key] = true;
        $values = [];
        if ($property->default !== null) {
            $values[] = [$this->engine->returns(new Frame(new Graph($property->default), 'default:' . $identity), $depth), []];
        }
        foreach ($index->writes($property) as [$graph, $write]) {
            $owner = new Frame($graph, 'property-origin:' . $graph->body->symbol);
            if ($graph->body->symbol !== $frame->graph->body->symbol) {
                $this->engine->context->bodyExpansions++;
                $this->engine->context->bodies[$graph->body->symbol] = ($this->engine->context->bodies[$graph->body->symbol] ?? 0) + 1;
            }
            $value = $this->engine->value($owner, $write->operands[1], $depth);
            $block = $graph->positions[$write->result][0];
            $values[] = [(new Guards($this->engine))->at($owner, $block, $value, $depth), []];
        }
        unset($this->engine->context->active[$key]);
        return $values === [] ? $this->engine->context->reference($frame, $identity, $address->source, $property->type, 'UNINITIALIZED_PROPERTY') : (new Choices())->make($values);
    }
}
