<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Origins;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Locates the lexical definition captured by an observed closure.
 * @visibility parent
 */
final class Captures
{
    /**
     * Follows a closure invocation that can write the requested lexical capture.
     */
    public function effect(Evaluator $engine, Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        $local = $caller->graph->definitions[$address] ?? null;
        if ($call->operation !== 'invoke' || $local?->operation !== 'local' || $engine->context->index->target($caller->graph, $call) !== '') {
            return null;
        }
        if (!$this->contains($engine, $caller, $local->name)) {
            return null;
        }
        return (new \Deriver\Deriver\Evaluator\Invocation\Dispatch($engine))->apply($caller, $call, $depth, function (string $target, ?Term $receiver, ?Term $closure) use ($engine, $caller, $call, $local, $address, $depth): Term {
            $graph = $engine->context->index->graph($target);
            if ($closure?->kind === \Deriver\Deriver\Evaluator\TermKind::Closure && ($closure->attributes['context'] ?? '') === $caller->identity && ($graph?->body->captures[$local->name] ?? false) === true) {
                $calls = new \Deriver\Deriver\Evaluator\Calls($engine);
                $bound = $calls->captures($calls->bind($caller, $call, $graph, $receiver, $depth), $closure);
                foreach ($graph->definitions as $definition) {
                    if ($definition->operation === 'local' && $definition->name === $local->name) {
                        return $calls->finalStorage($bound, $definition->result, $depth);
                    }
                }
            }
            [$block, $offset] = $caller->graph->positions[$call->result];
            return (new Storage($engine))->search($caller, $address, $block, $offset, $depth);
        });
    }

    /**
     * Checks whether a local escapes through a reference capture in this callable.
     */
    public function contains(Evaluator $engine, Frame $owner, string $name): bool
    {
        foreach ($owner->graph->definitions as $creation) {
            if ($creation->operation === 'closure' && ($engine->context->index->graph($creation->name)?->body->captures[$name] ?? false) === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolves a lexical capture without requiring a callback invocation history.
     */
    public function value(Evaluator $engine, Frame $closure, string $name, int $depth): ?Term
    {
        $alternatives = [];
        foreach ($engine->context->index->program->closureOwners($closure->graph->body->symbol) as $symbol) {
            $graph = $engine->context->index->graph($symbol);
            if ($graph === null) {
                continue;
            }
            foreach ($graph->definitions as $creation) {
                if ($creation->operation !== 'closure' || $creation->name !== $closure->graph->body->symbol) {
                    continue;
                }
                foreach ($engine->context->entryFrames[strtolower($symbol)] ?? [new Frame($graph, 'source:' . $symbol)] as $owner) {
                    $value = $this->at($engine, $owner, $creation, $name, $depth);
                    $alternatives[] = [$value, []];
                }
            }
        }
        return $alternatives === [] ? null : (new Choices($engine->context->provenance))->make($alternatives);
    }

    /**
     * Reads the captured storage version at the closure definition.
     */
    public function at(Evaluator $engine, Frame $owner, Instruction $creation, string $name, int $depth): Term
    {
        [$block, $offset] = $owner->graph->positions[$creation->result];
        foreach ($owner->graph->definitions as $local) {
            if ($local->operation === 'local' && $local->name === $name) {
                return (new Storage($engine))->search($owner, $local->result, $block, $offset, $depth);
            }
        }
        return (new Origins($engine))->parameter($owner, new \Deriver\Deriver\Evaluator\Call\BoundInstruction('capture:' . $name, 'local', $creation->source, name: $name), $depth);
    }
}
