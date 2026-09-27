<?php

declare(strict_types=1);

namespace Deriver\Model\Compilation;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * Lowers trusted plans into the same SSA instructions and control-flow blocks as PHP.
 * @visibility root
 */
final class PlanCompiler
{
    /**
     * @var array<int, list<Instruction>> Ordered instructions.
     */
    public array $instructions = [0 => []];
    /**
     * @var array<int, Terminator> Control-flow edges.
     */
    public array $terminators = [];
    /**
     * Current block.
     */
    public int $current = 0;
    /**
     * SSA sequence.
     */
    public int $sequence = 0;

    /**
     * @param SourceRef $source Model declaration provenance
     */
    public function __construct(public readonly SourceRef $source)
    {
    }

    /**
     * Compiles an acyclic semantic plan.
     * @param ModelDescriptor $descriptor Model signature and identity
     * @param SemanticPlan $plan Ordered API semantics
     * @param CallableGraph|null $declaration Optional source declaration supplying lexical method metadata
     * @return CallableGraph Executable abstract graph
     */
    public function compile(ModelDescriptor $descriptor, SemanticPlan $plan, ?CallableGraph $declaration = null): CallableGraph
    {
        $this->actions($plan->actions);
        $parameters = [];
        foreach ($descriptor->signature->parameters as $parameter) {
            $default = null;
            if ($parameter->default !== null) {
                $instruction = new Instruction('default', 'constant', $this->source, 'r0', constant: $parameter->default);
                $default = new CallableGraph($descriptor->symbol . ':default:' . $parameter->name, [], [new BasicBlock(0, [$instruction], new Terminator('return', 'r0'))], $this->source);
            }
            $parameters[] = new Parameter($parameter->name, $parameter->type, $parameter->byReference, $parameter->variadic, $default);
        }
        $blocks = [];
        foreach ($this->instructions as $id => $instructions) {
            $blocks[$id] = new BasicBlock($id, $instructions, $this->terminators[$id] ?? new Terminator('return'));
        }
        $class = $declaration->className ?? (str_contains($descriptor->symbol, '::') ? explode('::', $descriptor->symbol, 2)[0] : '');
        return new CallableGraph($descriptor->symbol, $parameters, $blocks, $this->source, $descriptor->signature->returnType, byReference: $descriptor->signature->byReference, className: $class, allowExtraArguments: $descriptor->signature->allowExtraArguments, visibility: $declaration->visibility ?? 'public', static: $declaration->static ?? false);
    }

    /**
     * Emits a model expression as shared solver instructions.
     * @param Expression $expression Declarative expression
     * @return string Result register
     * @throws InvalidInputException If the plan contains an unsupported opcode
     */
    public function expression(Expression $expression): string
    {
        if ($expression->operation === 'location-read' && $expression->location !== null) {
            return $this->emit('read', [(new PlanLocations($this))->address($expression->location)]);
        }
        $operands = array_map(fn (Expression $operand): string => $this->expression($operand), $expression->operands);
        if ($expression->operation === 'parameter') {
            return $this->emit('read', [$this->emit('local', name: $expression->name)]);
        }
        if ($expression->operation === 'state') {
            return $this->emit('read', [$this->emit('model-state-address', [$operands[0]], name: $expression->name)]);
        }
        if (!in_array($expression->operation, ['constant', 'binary', 'unary', 'cast', 'intrinsic', 'external', 'array-read', 'array-set'], true)) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: unsupported expression ' . $expression->operation);
        }
        return $this->emit($expression->operation, $operands, $expression->name, $expression->constant);
    }

    /**
     * Lowers ordered actions, keeping callback exceptions and effects in the core.
     * @param list<Action> $actions Plan actions
     * @throws InvalidInputException If the plan uses an unsupported action
     */
    public function actions(array $actions): void
    {
        foreach ($actions as $action) {
            if ((new PlanActions($this))->apply($action)) {
                return;
            }
        }
    }

    /**
     * Creates correlated model branches with one continuation.
     * @param Action $action Conditional action
     * @param string $condition Predicate register
     */
    public function choice(Action $action, string $condition): void
    {
        $yes = count($this->instructions);
        $no = $yes + 1;
        $join = $yes + 2;
        $this->instructions[$yes] = [];
        $this->instructions[$no] = [];
        $this->instructions[$join] = [];
        $this->terminators[$this->current] = new Terminator('branch', $condition, [$yes, $no]);
        $this->current = $yes;
        $this->actions($action->yes);
        $this->terminators[$this->current] ??= new Terminator('jump', targets: [$join]);
        $this->current = $no;
        $this->actions($action->no);
        $this->terminators[$this->current] ??= new Terminator('jump', targets: [$join]);
        $this->current = $join;
    }

    /**
     * Emits one model instruction with stable model provenance.
     * @param string $operation Instruction operation
     * @param list<string> $operands Register inputs
     * @param string $name Operation-specific name
     * @param Term|null $constant Literal payload
     * @param list<Argument> $arguments Call arguments
     * @param array<string, scalar|null> $attributes Operation metadata
     * @return string Result register
     */
    public function emit(string $operation, array $operands = [], string $name = '', ?Term $constant = null, array $arguments = [], array $attributes = []): string
    {
        $register = 'm' . $this->sequence++;
        $this->instructions[$this->current][] = new Instruction($this->source->path . ':' . $register, $operation, $this->source, $register, $operands, $name, $constant, $arguments, $attributes);
        return $register;
    }
}
