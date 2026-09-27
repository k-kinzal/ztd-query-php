<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration\Traits;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;

/**
 * Rebinds already lowered trait property initializers into consuming class scope.
 * @visibility root
 */
final class PropertyScope
{
    /**
     * Copies a pure default graph while preserving its source file and expression identity.
     * @param CallableGraph $body Trait property default
     * @param string $class Consuming class
     * @return CallableGraph Class-bound initializer
     */
    public function graph(CallableGraph $body, string $class): CallableGraph
    {
        $blocks = [];
        foreach ($body->blocks as $id => $block) {
            $instructions = [];
            foreach ($block->instructions as $instruction) {
                $attributes = $instruction->attributes;
                if (isset($attributes['class'])) {
                    $attributes['class'] = $class;
                }
                $instructions[] = new Instruction($instruction->id, $instruction->operation, $instruction->source, $instruction->result, $instruction->operands, $instruction->name, $instruction->constant, $instruction->arguments, $attributes);
            }
            $blocks[$id] = new BasicBlock($id, $instructions, $block->terminator, $block->loopHeader);
        }
        return new CallableGraph($class . ':trait-property:' . $body->symbol, $body->parameters, $blocks, $body->source, $body->returnType, $body->byReference, $body->strict, $class, $body->captures, $body->regions, $body->allowExtraArguments, $body->visibility, $body->static, $body->abstract);
    }
}
