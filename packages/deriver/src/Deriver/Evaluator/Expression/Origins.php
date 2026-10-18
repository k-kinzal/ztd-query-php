<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Expands formal inputs and declared property origins within the captured source world.
 * @visibility parent
 */
final class Origins
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly \Deriver\Deriver\Evaluator\Evaluator $engine, private readonly bool $truth = false, private readonly int $wanted = 3, private readonly ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null)
    {
    }

    /**
     * Expands a bound or caller-derived input and retains natural input boundaries.
     */
    public function parameter(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $local, int $depth): Term
    {
        $parameters = array_column($frame->graph->body->parameters, null, 'name');
        $parameter = $parameters[$local->name] ?? null;
        $binding = $frame->bindings[$local->name] ?? null;
        if ($binding instanceof Term) {
            return $this->bindingEvidence($frame, $local, $binding);
        }
        if ($binding instanceof \Deriver\Deriver\Evaluator\Scope\Binding) {
            return $this->bindingEvidence($frame, $local, ($this->finite !== null ? $binding->finite($this->engine, $parameter->type ?? 'mixed', $depth, $this->finite) : ($this->truth ? $binding->truth($this->engine, $parameter->type ?? 'mixed', $depth, $this->wanted) : $binding->value($this->engine, $parameter->type ?? 'mixed', $depth))));
        }
        $type = $parameter->type ?? ($local->name === 'this' ? $frame->graph->body->className : 'mixed');
        if ($frame->invocation && $parameter?->default !== null) {
            return (new ReturnEvaluator($this->engine))->evaluate(new \Deriver\Deriver\Evaluator\Scope\Frame(new BodyIndex($parameter->default), $frame->identity . ':default:' . $local->name), $depth, $this->truth, $this->wanted, $this->finite);
        }
        if (array_key_exists($local->name, $frame->graph->body->captures)) {
            $captured = (new \Deriver\Deriver\Evaluator\Memory\Captures())->value($this->engine, $frame, $local->name, $depth);
            if ($captured !== null) {
                return $captured;
            }
        }
        if (!$frame->invocation && $parameter !== null) {
            $key = ($this->truth ? 'truth:' . $this->wanted . ':' . ($this->finite->identity ?? '') . ':' : '') . $frame->identity . ':origin:$' . $local->name;
            return $this->engine->context->search->resolve(
                $key,
                fn (): Term => $this->callers($frame, $local->name, $depth) ?? $this->unbound($frame, $local, $type, true),
                fn (): Term => $this->engine->context->reference($frame, '$' . $local->name, $local->source, $type, 'CYCLE', \Deriver\Deriver\Evaluator\TermKind::Recursive),
            );
        }
        return $this->unbound($frame, $local, $type, $parameter !== null);
    }

    /**
     * Retains a natural input boundary with its source identity and known type.
     */
    public function unbound(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $local, string $type, bool $parameter): Term
    {
        if (in_array($local->name, ['_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES', '_SESSION', 'GLOBALS'], true)) {
            return $this->engine->context->configuration->environment['$' . $local->name] ?? $this->engine->context->reference($frame, 'global:' . $local->name, $local->source, 'array');
        }
        $parameters = array_column($frame->graph->body->parameters, null, 'name');
        $source = $parameter ? ($parameters[$local->name]->source ?? $local->source) : $local->source;
        return $this->engine->context->reference($frame, '$' . $local->name, $source, $type, !$parameter && $local->name !== 'this' ? 'UNRESOLVED_LOCAL' : 'EXTERNAL_INPUT');
    }

    /**
     * Finds and expands actual arguments belonging to the requested declaration.
     * Returns null when same-name discovery candidates cannot call this declaration,
     * so its parameters remain external inputs. Matching calls retain empty arguments.
     */
    public function callers(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $name, int $depth): ?Term
    {
        $sites = $this->engine->context->index->callers($frame->graph->body->symbol);
        if ($sites === []) {
            return null;
        }
        $branches = [];
        $guards = [];
        $matched = false;
        $group = $frame->identity . ':caller';
        foreach ($sites as [$graph, $instruction]) {
            foreach ($this->engine->context->entryFrames[strtolower($graph->body->symbol)] ?? [new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'source:' . $graph->body->symbol)] as $caller) {
                $guards[] = [$group => $instruction->source->id()];
                $branches[] = function (int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null) use ($frame, $caller, $instruction, $name, $depth, &$matched): Term {
                    if (($reason = $this->engine->context->work()) !== null) {
                        return $this->engine->context->reference($frame, '$' . $name, $instruction->source, reason: $reason, kind: \Deriver\Deriver\Evaluator\TermKind::Deferred);
                    }
                    return (new \Deriver\Deriver\Evaluator\Invocation\Dispatch($this->engine))->apply($caller, $instruction, $depth, function (string $target, ?Term $receiver) use ($frame, $caller, $instruction, $name, $depth, &$matched, $wanted, $finite): Term {
                        if ($target !== '' && strcasecmp($target, $frame->graph->body->symbol) !== 0) {
                            return new Term(\Deriver\Deriver\Evaluator\TermKind::Choice);
                        }
                        $matched = true;
                        if ($target === '') {
                            return $this->engine->context->reference($frame, '$' . $name, $instruction->source, reason: 'UNRESOLVED_DISPATCH');
                        }
                        $bound = (new \Deriver\Deriver\Evaluator\Calls($this->engine))->bind($caller, $instruction, $frame->graph, $receiver, $depth);
                        return (new self($this->engine, $this->truth, $wanted, $finite))->parameter($bound, new \Deriver\Deriver\Evaluator\Call\BoundInstruction('input:' . $name, 'local', $instruction->source, name: $name), $depth);
                    });
                };
            }
        }
        $result = $this->truth
            ? (new \Deriver\Deriver\Evaluator\Predicate\Projection($this->engine->context->provenance, $this->finite))->collect($branches, $this->wanted)
            : (new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->make(array_map(static fn (Term $value, array $guard): array => [$value, $guard], $this->engine->context->search->all($branches), $guards));
        return $matched || (new \Deriver\Deriver\Evaluator\Enumeration\Enumerator($result))->next() !== null ? $result : null;
    }

    /**

     * Keeps the actual/formal relation at the point a lazy binding is demanded.

     */
    public function bindingEvidence(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $local, Term $value): Term
    {
        if (array_key_exists($local->name, $frame->graph->body->captures)) {
            $binding = $frame->bindings[$local->name] ?? null;
            $version = $binding instanceof \Deriver\Deriver\Evaluator\Scope\Binding ? implode(':', $binding->position ?? []) : 'unresolved';
            return $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Capture, $local->source, ['name' => $local->name, 'owner' => $frame->graph->body->symbol, 'context' => $frame->identity, 'mode' => $frame->graph->body->captures[$local->name] ? 'reference' : 'value', 'version' => $version]);
        }
        $origin = $frame->origin;
        if ($origin === null) {
            return $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ExternalInput, $local->source, ['input' => $local->name, 'context' => $frame->identity]);
        }
        $call = $origin->frame->graph->definitions[$origin->register] ?? null;
        if ($call === null) {
            return $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ExternalInput, $local->source, ['input' => $local->name, 'context' => $frame->identity, 'version' => 'source-origin']);
        }
        $parameters = array_column($frame->graph->body->parameters, null, 'name');
        $binding = $frame->bindings[$local->name] ?? null;
        $actual = $binding instanceof \Deriver\Deriver\Evaluator\Scope\Binding ? ($binding->frame->graph->definitions[$binding->register]->source ?? $call->source) : $call->source;
        $formal = $parameters[$local->name]->source ?? $frame->graph->body->source;
        $position = array_search($local->name, array_column($frame->graph->body->parameters, 'name'), true);
        $mapping = $position === false ? 'receiver' : 'position:' . $position;
        foreach ($call->arguments as $argument) {
            if ($argument->name === $local->name || $argument->unpack) {
                $mapping = $argument->unpack ? 'unpack' : 'name:' . $local->name;
            }
        }
        return $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ArgumentBinding, $call->source, ['parameter' => $local->name, 'caller' => $origin->frame->graph->body->symbol, 'callee' => $frame->graph->body->symbol, 'call_site' => $call->source->id(), 'context' => $frame->identity, 'parent_context' => $origin->frame->identity, 'called_class' => $frame->calledClass, 'formal' => $formal->id(), 'actual' => $actual->id(), 'mapping' => $mapping]);
    }

    /**
     * Resolves the requested property and its candidate origins.
     */
    public function property(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $address, int $depth): Term
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
            if ($receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object && ($receiver->attributes['cast'] ?? false) === true) {
                return $receiver->operands[$name] ?? Term::constant(null);
            }
            if ($receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Enum && isset($receiver->operands[$name])) {
                return $receiver->operands[$name];
            }
            if ($receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                return $receiver;
            }
        }
        if ($address->operation === 'static-address') {
            $class = $index->className($index->literal($frame->graph, $address->operands[0]), $frame->graph->body->className, $frame->calledClass);
            $property = $index->property($class, $name);
        }
        if ($receiver !== null && $property?->visibility !== \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Private) {
            $property = $index->property((string) ($receiver->attributes['type'] ?? ''), $name) ?? $property;
        }
        if ($property === null) {
            return $this->engine->context->reference($frame, 'property:' . $name, $address->source, reason: 'UNRESOLVED_PROPERTY');
        }
        if ($receiver !== null) {
            $allocated = (new \Deriver\Deriver\Evaluator\Memory\PropertyEffects())->incoming($this->engine, $frame, $receiver, $name, $depth) ?? (new \Deriver\Deriver\Evaluator\Memory\Properties())->allocated($this->engine, $receiver, $property, $depth);
            if ($allocated !== null) {
                return $allocated;
            }
        }
        return (new \Deriver\Deriver\Evaluator\Memory\PropertyOrigins($this->engine))->value($frame, $address, $property, $depth);
    }
}
