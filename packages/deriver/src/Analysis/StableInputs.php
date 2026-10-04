<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Value\Term;

/**
 * Proves immutable entry bindings before using declared types in interrupted observations.
 * @visibility root
 */
final class StableInputs
{
    /**
     * A parameter type constrains entry only: any write, escape, or unknown symbol-table effect defeats this proof.
     * @param CallableGraph $body Observation owner, completely indexed within the recovery limit
     * @param array<string, Instruction> $definitions Complete register definitions
     * @param string $reason Interruption cause
     * @return array<string, Term> Typed residuals for bindings that cannot change
     */
    public function recover(CallableGraph $body, array $definitions, string $reason): array
    {
        if (str_starts_with($body->symbol, 'script:')) {
            return [];
        }
        $types = [];
        foreach ($body->parameters as $parameter) {
            if (!$parameter->byReference && preg_match('/\b(self|parent|static)\b/', $parameter->type) !== 1) {
                $types[$parameter->name] = $this->type($parameter);
            }
        }
        if (!$body->static && $body->className !== '') {
            $types['this'] = $body->className;
        }
        foreach ($body->regions as $region) {
            foreach ($region->catches as $catch) {
                unset($types[$catch->variable]);
            }
        }
        foreach ($definitions as $instruction) {
            if (in_array($instruction->operation, ['dynamic-local', 'symbol-table-boundary', 'unsupported', 'closure'], true) || $this->symbolEffect($instruction, $definitions)) {
                return [];
            }
            foreach ($instruction->operands as $operand) {
                $local = $definitions[$operand] ?? null;
                if ($local?->operation === 'local' && !in_array($instruction->operation, ['read', 'read-silent'], true)) {
                    unset($types[$local->name]);
                }
            }
        }
        return array_map(static fn (string $type): Term => Term::opaque($reason, $type), $types);
    }

    /**
     * Keeps implicit nullable defaults in a PHP 8.3 entry bound.
     * @param \Deriver\ControlFlow\Parameter $parameter Captured signature parameter
     * @return string Conservative entry type
     */
    public function type(\Deriver\ControlFlow\Parameter $parameter): string
    {
        if ($parameter->variadic) {
            return 'array';
        }
        $default = $parameter->default->blocks[0]->instructions[0] ?? null;
        $nullable = $default?->operation === 'constant-fetch' && strtolower($default->name) === 'null';
        return $nullable && $parameter->type !== 'mixed' && !in_array('null', explode('|', $parameter->type), true) ? $parameter->type . '|null' : $parameter->type;
    }

    /**
     * Dynamic function calls may resolve to extract(), which changes the caller's symbols.
     * @param Instruction $instruction Candidate invocation
     * @param array<string, Instruction> $definitions Captured register definitions
     * @return bool Whether a caller symbol-table effect is possible
     */
    public function symbolEffect(Instruction $instruction, array $definitions): bool
    {
        if ($instruction->operation !== 'invoke') {
            return false;
        }
        $name = $definitions[$instruction->operands[0] ?? '']->constant->literal ?? null;
        return !is_string($name) || strtolower(substr($name, (int) strrpos('\\' . $name, '\\'))) === 'extract';
    }
}
