<?php

declare(strict_types=1);

namespace Deriver\Internal\Frontend\Php\Traits;

use Deriver\Internal\IR\BasicBlock;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;

/**
 * Rebinds already lowered trait property initializers into consuming class scope.
 * @visibility root
 */
final class PropertyScope
{
    /**
     * Copies a pure default graph while preserving its source file and expression identity.
     * @param CallableIR $body Trait property default
     * @param string $class Consuming class
     * @return CallableIR Class-bound initializer
     */
    public function graph(CallableIR $body, string $class): CallableIR
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
        return new CallableIR($class . ':trait-property:' . $body->symbol, $body->parameters, $blocks, $body->source, $body->returnType, $body->byReference, $body->strict, $class, $body->captures, $body->regions, $body->allowExtraArguments, $body->visibility, $body->static, $body->abstract);
    }
}
