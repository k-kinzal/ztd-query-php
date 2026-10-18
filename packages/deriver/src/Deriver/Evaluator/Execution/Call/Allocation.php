<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Allocates distinct object identities and applies shallow clone semantics.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Allocation
{
    /**
     * @param Evaluator $machine Shared evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Allocates or clones an object before invoking constructor or __clone.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction \Deriver\Deriver\Evaluator\Execution\Call\Allocation site
     * @param State $state Calling state
     * @param list<PassedArgument> $arguments Constructor actuals
     * @return list<State> Allocated object or exceptional paths
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, array $arguments): array
    {
        $access = new Access($this->machine);
        $failure = $access->check($caller, $instruction, $state, $arguments);
        if ($failure !== null) {
            return $failure;
        }
        $source = $state->value($instruction->operands[0]);
        $clone = $instruction->operation === 'clone';
        $class = $access->name($source, $caller, $state, ($instruction->attributes['literal-class'] ?? true) === true) ?? '';
        $dispatch = new Dispatch($this->machine->context->program);
        if ($dispatch->method($class, '__destruct') !== null) {
            $paths = (new Conversions($this->machine))->boundary($caller, $instruction, $state, 'destructor-lifetime');
            foreach ($paths as $path) {
                if ($path->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                    $path->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Return, Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE'));
                }
            }
            return $paths;
        }
        $object = $this->record($instruction, $state, $class, $source, $clone);
        $root = 'object:' . $object->literal;
        $states = $clone ? [$state] : $this->initialize($class, $object, $state);
        $target = $dispatch->method($class, $clone ? '__clone' : '__construct');
        if (!$clone && $target === null && isset($this->machine->context->evaluators->evaluators[strtolower($class . '::__construct')])) {
            $target = $class . '::__construct';
        }
        $result = [];
        foreach ($states as $initialized) {
            $completed = [$initialized];
            if ($initialized->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw) {
                $completed = $target === null ? ((new Invocation($this->machine))->apply($class, $clone ? '__clone' : '__construct', $arguments, $initialized, $instruction, $object, $caller->strict) ?? [$initialized]) : (new CallExecutor($this->machine))->symbol($target, $arguments, $initialized, $instruction, $object, strict: $caller->strict);
            }
            foreach ($completed as $next) {
                unset($next->memory->cloneWrites[$root]);
                if ($next->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                    $next->registers[$instruction->result] = $object;
                }
                $result[] = $next;
            }
        }
        return $result;
    }

    /**
     * Creates one fresh identity and its ordinary and abstract state records.
     * @param Instruction $instruction \Deriver\Deriver\Evaluator\Execution\Call\Allocation site
     * @param State $state Current memory
     * @param string $class Resolved runtime class
     * @param Term $source Class name or cloned object
     * @param bool $clone Whether to copy source properties
     * @return Term Fresh object identity
     */
    public function record(Instruction $instruction, State $state, string $class, Term $source, bool $clone): Term
    {
        $id = $state->memory->fresh($instruction->id . ':object');
        $object = new Term(\Deriver\Deriver\Evaluator\TermKind::Object, $id, attributes: ['class' => $class]);
        $state->memory->classes[$id] = $class;
        (new StateStorage($this->machine->context))->initialize($state, $id, $clone && is_string($source->literal) ? $source->literal : null);
        $root = 'object:' . $id;
        $state->memory->cells[$root] = $clone && is_string($source->literal) ? ($state->memory->cells['object:' . $source->literal] ?? Term::array([], true)) : Term::array([]);
        if ($clone) {
            $state->memory->propertyTypes[$root] = $state->memory->propertyTypes['object:' . $source->literal] ?? [];
            $state->memory->cloneWrites[$root] = array_fill_keys(array_keys($state->memory->cells[$root]->operands), true);
        }
        return $object;
    }

    /**
     * Evaluates property defaults separately for each object allocation.
     * @param string $class Runtime class
     * @param Term $object New object reference
     * @param State $state Allocated path
     * @param list<string> $seen Inheritance cycle guard
     * @return list<State> Initialized alternatives
     */
    public function initialize(string $class, Term $object, State $state, array $seen = []): array
    {
        $declaration = $this->machine->context->program->classes()[strtolower($class)] ?? null;
        if ($declaration === null) {
            (new Properties($this->machine->context->program))->initialize($class, $object, $state);
            return [$state];
        }
        if (in_array($class, $seen, true)) {
            return [$state];
        }
        $states = $declaration->parent === '' ? [$state] : $this->initialize($declaration->parent, $object, $state, [...$seen, $class]);
        foreach ($declaration->composed ? [] : $declaration->traits as $trait) {
            $expanded = [];
            foreach ($states as $path) {
                array_push($expanded, ...$this->initialize($trait, $object, $path, [...$seen, $class]));
            }
            $states = $expanded;
        }
        foreach ($declaration->properties as $property) {
            if ($property->static) {
                continue;
            }
            $next = [];
            foreach ($states as $path) {
                $slot = $property->visibility === \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Private ? $property->className . '::' . $property->name : $property->name;
                $address = new Location('object:' . $object->literal, [$slot]);
                $path->memory->propertyTypes[$address->root][$slot] = (new TypeBinding($this->machine->context))->scope($property->type, $property->className, $class);
                if ($property->default === null) {
                    $path->memory->write($address, $property->type === 'mixed' ? Term::constant(null) : new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized, attributes: ['type' => $property->type]));
                    $next[] = $path;
                    continue;
                }
                foreach ($this->machine->run($property->default, new State(clone $path->memory)) as $exit) {
                    $branch = $path->fork();
                    $branch->memory = $exit->memory;
                    if ($exit->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw) {
                        $branch->completion = $exit->completion;
                    } else {
                        $branch->memory->write($address, $exit->completion->value ?? Term::constant(null));
                    }
                    $next[] = $branch;
                }
            }
            $states = $next;
        }
        return $states;
    }
}
