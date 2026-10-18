<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;

/**
 * Gives a declaration origin the same lazy inputs as an ordinary call.
 * @visibility parent
 */
final class Origin
{
    /**
     * Builds an input-only frame; source instructions are never executed to select a evaluator.
     * @return array{Frame, Instruction}
     */
    public function call(BodyIndex $graph): array
    {
        $source = $graph->body;
        $instructions = [];
        $arguments = [];
        foreach ($source->parameters as $parameter) {
            $local = 'origin-local:' . $parameter->name;
            $read = 'origin-read:' . $parameter->name;
            $instructions[] = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($local, 'local', $source->source, $local, name: $parameter->name);
            $instructions[] = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($read, 'read', $source->source, $read, [$local]);
            $arguments[] = new Argument($read, $parameter->name);
        }
        $body = new CallableGraph($source->symbol, $source->parameters, [new BasicBlock(0, $instructions, new Terminator(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Return))], $source->source, className: $source->className);
        return [new Frame(new BodyIndex($body), 'origin-input:' . $source->symbol), new \Deriver\Deriver\Evaluator\Call\BoundInstruction('origin-call:' . $source->symbol, 'invoke', $source->source, arguments: $arguments)];
    }
}
