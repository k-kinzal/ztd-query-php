<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Program;
use Deriver\Query\Query;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Recovers bounded syntactic value candidates after execution stops before an observation.
 * Reachability and every unresolved dependency remain open under the interruption frontier.
 * @visibility root
 */
final class PartialObservation
{
    /**
     * @var array<string, Instruction> Definitions in the already compiled observation owner
     */
    private array $definitions = [];
    /**
     * @var array<string, Term> Reconstructed values, including cycle sentinels
     */
    private array $values = [];
    /**
     * @var array<string, Term> Proven immutable entry bindings
     */
    private array $inputs = [];
    private int $remaining = 256;

    /**
     * @param Program $program Captured graph world
     * @param string $reason Interruption frontier
     */
    public function __construct(public readonly Program $program, public readonly string $reason)
    {
    }

    /**
     * Recovers only expression queries; mutable state and returns need execution context.
     * @param Query $query Interrupted request
     * @return Alternative|null Candidate expressions or no supported observation
     */
    public function recover(Query $query): ?Alternative
    {
        $references = $query instanceof ValueQuery && $query->projection->path === [] && $query->projection->slot === null ? ['value' => $query->expression] : ($query instanceof TupleQuery ? $query->values : []);
        if ($references === []) {
            return null;
        }
        $body = $this->program->callable(reset($references)->callable);
        $complete = true;
        foreach ($body->blocks ?? [] as $block) {
            foreach ($block->instructions as $instruction) {
                if (count($this->definitions) >= 4096) {
                    $complete = false;
                    break 2;
                }
                $this->definitions[$instruction->result] = $instruction;
            }
        }
        $this->inputs = $complete && $body !== null ? (new StableInputs())->recover($body, $this->definitions, $this->reason) : [];
        $values = [];
        foreach ($references as $name => $reference) {
            $values[$name] = $this->value($reference->register);
        }
        return new Alternative($values);
    }

    /**
     * Follows constants, evaluated argument wrappers, and string composition without executing code.
     * @param string $register Requested SSA result
     * @param int $depth Remaining host recursion depth
     * @return Term Candidate with explicit gaps
     */
    public function value(string $register, int $depth = 32): Term
    {
        if (isset($this->values[$register])) {
            return $this->values[$register];
        }
        $unknown = Term::opaque($this->reason);
        $this->values[$register] = $unknown;
        $instruction = $this->definitions[$register] ?? null;
        if ($instruction === null || --$this->remaining < 0 || $depth === 0) {
            return $unknown;
        }
        $value = $unknown;
        if ($instruction->operation === 'constant') {
            $value = $instruction->constant ?? $unknown;
        } elseif ($instruction->operation === 'copy' || $instruction->operation === 'argument' && ($instruction->attributes['address'] ?? false) === false) {
            $index = $instruction->operation === 'argument' ? 1 : 0;
            $value = $this->value($instruction->operands[$index] ?? '', $depth - 1);
        } elseif (in_array($instruction->operation, ['read', 'read-silent'], true)) {
            $value = $this->read($instruction);
        } elseif ($instruction->operation === 'binary' && $instruction->name === '.') {
            $left = $this->value($instruction->operands[0], $depth - 1);
            $right = $this->value($instruction->operands[1], $depth - 1);
            $parts = array_map(static fn (Term $part): Term => ($part->attributes['type'] ?? '') === 'string' ? $part : (new Operations())->cast('string', $part), [$left, $right]);
            $value = new Term('concat', operands: $parts, attributes: ['type' => 'string']);
        }
        return $this->values[$register] = $value;
    }
    /**
     * Recovers only a read of a proven immutable local binding.
     * @param Instruction $instruction Requested read
     * @return Term Declared input type or an untyped interruption residual
     */
    public function read(Instruction $instruction): Term
    {
        $local = $this->definitions[$instruction->operands[0] ?? ''] ?? null;
        return $local?->operation === 'local' ? ($this->inputs[$local->name] ?? Term::opaque($this->reason)) : Term::opaque($this->reason);
    }

}
