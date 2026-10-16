<?php

declare(strict_types=1);

namespace Deriver\Candidate\Memory;

use Deriver\Candidate\Choices;
use Deriver\Candidate\Derivation;
use Deriver\Candidate\Frame;
use Deriver\Candidate\Origins;
use Deriver\Candidate\Storage;
use Deriver\ControlFlow\Instruction;
use Deriver\Value\Term;

/**
 * Locates the lexical definition captured by an observed closure.
 * @visibility parent
 */
final class Captures
{
    /**
     * Resolves a lexical capture without requiring a callback invocation history.
     */
    public function value(Derivation $engine, Frame $closure, string $name, int $depth): ?Term
    {
        $alternatives = [];
        foreach ($engine->context->index->program->symbols() as $symbol) {
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
        return $alternatives === [] ? null : (new Choices())->make($alternatives);
    }

    /**
     * Reads the captured storage version at the closure definition.
     */
    public function at(Derivation $engine, Frame $owner, Instruction $creation, string $name, int $depth): Term
    {
        [$block, $offset] = $owner->graph->positions[$creation->result];
        foreach ($owner->graph->definitions as $local) {
            if ($local->operation === 'local' && $local->name === $name) {
                return (new Storage($engine))->search($owner, $local->result, $block, $offset, $depth);
            }
        }
        return (new Origins($engine))->parameter($owner, new Instruction('capture:' . $name, 'local', $creation->source, name: $name), $depth);
    }
}
