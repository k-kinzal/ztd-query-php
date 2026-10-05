<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Invocation;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;

/**
 * Gives a declaration origin the same lazy inputs as an ordinary call.
 * @visibility root
 */
final class Origin
{
    /**
     * Builds an input-only frame; source instructions are never executed to select a model.
     * @return array{Frame, Instruction}
     */
    public function call(Graph $graph): array
    {
        $source = $graph->body;
        $instructions = [];
        $arguments = [];
        foreach ($source->parameters as $parameter) {
            $local = 'origin-local:' . $parameter->name;
            $read = 'origin-read:' . $parameter->name;
            $instructions[] = new Instruction($local, 'local', $source->source, $local, name: $parameter->name);
            $instructions[] = new Instruction($read, 'read', $source->source, $read, [$local]);
            $arguments[] = new Argument($read, $parameter->name);
        }
        $body = new CallableGraph($source->symbol, $source->parameters, [new BasicBlock(0, $instructions, new Terminator('return'))], $source->source, className: $source->className);
        return [new Frame(new Graph($body), 'origin-input:' . $source->symbol), new Instruction('origin-call:' . $source->symbol, 'invoke', $source->source, arguments: $arguments)];
    }
}
