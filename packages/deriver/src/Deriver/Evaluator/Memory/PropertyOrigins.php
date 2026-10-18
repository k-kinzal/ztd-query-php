<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Guards;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Invocation\Bodies;
use Deriver\Deriver\Evaluator\Invocation\Dispatch;
use Deriver\Deriver\Evaluator\Invocation\Origin;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Collects stored values from selected implementations, never bare assignment operands.
 * @visibility parent
 */
final class PropertyOrigins
{
    /**
     * Uses the ordinary storage evaluator for every mutation form.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Resolves declared initialization without collecting method writes.
     */
    public function initial(PropertyDeclaration $property, int $depth): Term
    {
        $identity = $property->className . '::$' . $property->name;
        if ($property->default !== null) {
            return $this->engine->returns(new Frame(new BodyIndex($property->default), 'default:' . $identity), $depth);
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Reference, $identity, attributes: ['type' => $property->type, 'reason' => 'UNINITIALIZED_PROPERTY']);
    }

    /**
     * Collects candidate mutation results while retaining recursive dependencies.
     */
    public function value(Frame $frame, Instruction $address, PropertyDeclaration $property, int $depth): Term
    {
        $context = $this->engine->context;
        $identity = $property->className . '::$' . $property->name;
        $key = $frame->identity . ':property:' . $identity;
        if (isset($context->active[$key])) {
            return $context->reference($frame, $identity, $address->source, $property->type, 'CYCLE', \Deriver\Deriver\Evaluator\TermKind::Recursive);
        }
        $context->active[$key] = true;
        $values = $property->default === null ? [] : [[$this->initial($property, $depth), []]];
        $owners = [];
        foreach ($context->index->writes($property) as [$graph, $_]) {
            $owners[$graph->body->symbol] = $graph;
        }
        foreach ($owners as $graph) {
            array_push($values, ...$this->writes($frame, $graph, $property, $depth));
        }
        unset($context->active[$key]);
        return $values === [] ? $this->initial($property, $depth) : (new Choices($this->engine->context->provenance))->make($values);
    }

    /**
     * Selects evaluators before entering any source origin, then reuses reaching-write semantics.
     * @return list<array{Term, array<string, bool>}>
     */
    public function writes(Frame $request, BodyIndex $source, PropertyDeclaration $property, int $depth): array
    {
        $sites = $this->engine->context->index->callers($source->body->symbol);
        if ($sites === []) {
            [$inputs, $call] = (new Origin())->call($source);
            return $this->selectedWrites($request, $inputs, $call, $source->body->symbol, $property, $depth);
        }
        $values = [];
        foreach ($sites as [$caller, $call]) {
            foreach ($this->engine->context->entryFrames[strtolower($caller->body->symbol)] ?? [new Frame($caller, 'source:' . $caller->body->symbol)] as $inputs) {
                $value = (new Dispatch($this->engine))->apply($inputs, $call, $depth, fn (string $target, ?Term $receiver): Term => (new Choices($this->engine->context->provenance))->make($this->selectedWrites($request, $inputs, $call, $target, $property, $depth, $receiver)));
                $values[] = [$value, []];
            }
        }
        return $values;
    }

    /**
     * @return list<array{Term, array<string, bool>}>
     */
    public function selectedWrites(Frame $request, Frame $inputs, Instruction $call, string $target, PropertyDeclaration $property, int $depth, ?Term $receiver = null): array
    {
        $body = (new Bodies($this->engine))->select($inputs, $call, $target, $depth);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return [[$graph, []]];
        }
        if ($graph === null) {
            return [];
        }
        $owner = (new Calls($this->engine))->bind($inputs, $call, $graph, $receiver);
        if (str_ends_with(strtolower($target), '::__construct')) {
            $owner = new Frame($graph, $owner->identity . ':property:' . $property->name, $owner->bindings, [$property->name => $this->initial($property, $depth)], $owner->calls, true, origin: $owner->origin);
        }
        $values = [];
        foreach ($graph->definitions as $write) {
            $address = $graph->root($write);
            $declared = $address === null ? null : $this->engine->context->index->declaredProperty($graph, $address);
            if (!BodyIndex::writes($write) || $declared?->className !== $property->className || $declared->name !== $property->name) {
                continue;
            }
            if ($graph->body->symbol !== $request->graph->body->symbol) {
                $body->enter($this->engine->context);
            }
            [$block, $offset] = $graph->positions[$write->result];
            $value = (new Storage($this->engine))->search($owner, $address->result, $block, $offset + 1, $depth);
            $values[] = [(new Guards($this->engine))->at($owner, $block, $value, $depth), []];
        }
        return $values;
    }
}
