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
            return $this->bindingEvidence($frame, $local, $binding);
        }
        if ($binding instanceof Binding) {
            return $this->bindingEvidence($frame, $local, $binding->value($this->engine, $parameter->type ?? 'mixed', $depth));
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
        $parameters = array_column($frame->graph->body->parameters, null, 'name');
        $source = $parameter ? ($parameters[$local->name]->source ?? $local->source) : $local->source;
        return $this->engine->context->reference($frame, '$' . $local->name, $source, $type, !$parameter && $local->name !== 'this' ? 'UNRESOLVED_LOCAL' : 'EXTERNAL_INPUT');
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
            if (($reason = $this->engine->context->work()) !== null) {
                $values[] = [$this->engine->context->reference($frame, '$' . $name, $instruction->source, reason: $reason, kind: 'deferred'), []];
                break;
            }
            foreach ($this->engine->context->entryFrames[strtolower($graph->body->symbol)] ?? [new Frame($graph, 'source:' . $graph->body->symbol)] as $caller) {
                $value = (new Invocation\Dispatch($this->engine))->apply($caller, $instruction, $depth, function (string $target, ?Term $receiver) use ($frame, $caller, $instruction, $name, $depth): Term {
                    if ($target !== '' && strcasecmp($target, $frame->graph->body->symbol) !== 0) {
                        return new Term('choice');
                    }
                    if ($target === '') {
                        return $this->engine->context->reference($frame, '$' . $name, $instruction->source, reason: 'UNRESOLVED_DISPATCH');
                    }
                    $bound = (new Calls($this->engine))->bind($caller, $instruction, $frame->graph, $receiver, $depth);
                    return $this->parameter($bound, new Instruction('input:' . $name, 'local', $instruction->source, name: $name), $depth);
                });
                $guard = [$group => $instruction->source->id()];
                $values[] = [$value, $guard];
            }
        }
        return (new Choices())->make($values);
    }

    /**

     * Keeps the actual/formal relation at the point a lazy binding is demanded.

     */
    public function bindingEvidence(Frame $frame, Instruction $local, Term $value): Term
    {
        if (array_key_exists($local->name, $frame->graph->body->captures)) {
            $binding = $frame->bindings[$local->name] ?? null;
            $version = $binding instanceof Binding ? implode(':', $binding->position ?? []) : 'unresolved';
            return Evidence\Provenance::wrap($value, 'capture', $local->source, ['name' => $local->name, 'owner' => $frame->graph->body->symbol, 'context' => $frame->identity, 'mode' => $frame->graph->body->captures[$local->name] ? 'reference' : 'value', 'version' => $version]);
        }
        $origin = $frame->origin;
        if ($origin === null) {
            return Evidence\Provenance::wrap($value, 'external-input', $local->source, ['input' => $local->name, 'context' => $frame->identity, 'version' => $this->engine->context->configuration->environmentVersion]);
        }
        $call = $origin->frame->graph->definitions[$origin->register] ?? null;
        if ($call === null) {
            return Evidence\Provenance::wrap($value, 'external-input', $local->source, ['input' => $local->name, 'context' => $frame->identity, 'version' => 'source-origin']);
        }
        $parameters = array_column($frame->graph->body->parameters, null, 'name');
        $binding = $frame->bindings[$local->name] ?? null;
        $actual = $binding instanceof Binding ? ($binding->frame->graph->definitions[$binding->register]->source ?? $call->source) : $call->source;
        $formal = $parameters[$local->name]->source ?? $frame->graph->body->source;
        $position = array_search($local->name, array_column($frame->graph->body->parameters, 'name'), true);
        $mapping = $position === false ? 'receiver' : 'position:' . $position;
        foreach ($call->arguments as $argument) {
            if ($argument->name === $local->name || $argument->unpack) {
                $mapping = $argument->unpack ? 'unpack' : 'name:' . $local->name;
            }
        }
        return Evidence\Provenance::wrap($value, 'argument-binding', $call->source, ['parameter' => $local->name, 'caller' => $origin->frame->graph->body->symbol, 'callee' => $frame->graph->body->symbol, 'call_site' => $call->source->id(), 'context' => $frame->identity, 'parent_context' => $origin->frame->identity, 'called_class' => $frame->calledClass, 'formal' => $formal->id(), 'actual' => $actual->id(), 'mapping' => $mapping]);
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
        if ($address->operation === 'field-address') {
            $receiver = $this->engine->value($frame, $address->operands[0], $depth);
            if ($receiver->kind === 'enum' && isset($receiver->operands[$name])) {
                return $receiver->operands[$name];
            }
            if ($receiver->kind === 'throwable') {
                return $receiver;
            }
        }
        if ($address->operation === 'static-address') {
            $class = $index->className($index->literal($frame->graph, $address->operands[0]), $frame->graph->body->className, $frame->calledClass);
            $property = $index->property($class, $name);
        }
        if ($receiver !== null && $property?->visibility !== 'private') {
            $property = $index->property((string) ($receiver->attributes['type'] ?? ''), $name) ?? $property;
        }
        if ($property === null) {
            return $this->engine->context->reference($frame, 'property:' . $name, $address->source, reason: 'UNRESOLVED_PROPERTY');
        }
        if ($receiver !== null) {
            $allocated = (new Memory\PropertyEffects())->incoming($this->engine, $frame, $receiver, $name, $depth) ?? (new Memory\Properties())->allocated($this->engine, $receiver, $property, $depth);
            if ($allocated !== null) {
                return $allocated;
            }
        }
        return (new Memory\PropertyOrigins($this->engine))->value($frame, $address, $property, $depth);
    }
}
