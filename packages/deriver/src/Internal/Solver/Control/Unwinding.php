<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Control;

use Deriver\Internal\IR\Program;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Routes pending normal, return, throw, and jump completions through finally.
 * @visibility root
 */
final class Unwinding
{
    /**
     * @param Program $program Declaration hierarchy
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Finds the next handler or final completion destination.
     * @param State $state Completing execution path
     * @return bool Whether the path resumes within this callable
     */
    public function resume(State $state): bool
    {
        while ($state->handlers !== [] && count($state->handlers) > $state->completion->depth) {
            $handler = array_pop($state->handlers);
            if ($handler->phase === 'try' && $state->completion->kind === 'throw') {
                foreach ($handler->region->catches as $catch) {
                    if ($this->matches($state->completion->value ?? Term::opaque('EXCEPTION', 'Throwable'), $catch->types)) {
                        $state->handlers[] = new Handler($handler->region, 'catch');
                        if ($catch->variable !== '') {
                            $state->memory->write($state->local($catch->variable), $this->capture($state, $state->completion->value ?? Term::opaque('EXCEPTION')));
                        }
                        $state->block = $catch->block;
                        $state->completion = new Completion();
                        return true;
                    }
                }
            }
            if ($handler->phase !== 'finally' && $handler->region->finally !== null) {
                $state->handlers[] = new Handler($handler->region, 'finally', $state->completion);
                $state->block = $handler->region->finally;
                $state->completion = new Completion();
                return true;
            }
        }
        if ($state->completion->kind === 'jump') {
            $state->previous = $state->block;
            $state->block = $state->completion->target;
            $state->completion = new Completion();
            return true;
        }
        return false;
    }

    /**
     * Branches an unknown throwable through every feasible catch and its escape path.
     * @param State $state Completing path
     * @return list<State> Resumed or terminal paths
     */
    public function routes(State $state): array
    {
        if ($state->completion->kind !== 'throw' || ($state->completion->value?->attributes['uncertain'] ?? false) !== true) {
            $this->resume($state);
            return [$state];
        }
        $result = [];
        while ($state->handlers !== []) {
            $handler = array_pop($state->handlers);
            if ($handler->phase === 'try') {
                foreach ($handler->region->catches as $catch) {
                    $caught = $state->fork();
                    $caught->handlers[] = new Handler($handler->region, 'catch');
                    $caught->guard['catch:' . $handler->region->continuation . ':' . $catch->block] = true;
                    if ($catch->variable !== '') {
                        $caught->memory->write($caught->local($catch->variable), $this->capture($caught, $state->completion->value ?? Term::opaque('EXCEPTION')));
                    }
                    $caught->block = $catch->block;
                    $caught->completion = new Completion();
                    $result[] = $caught;
                    if (in_array('throwable', array_map(strtolower(...), $catch->types), true)) {
                        return $result;
                    }
                }
            }
            if ($handler->phase !== 'finally' && $handler->region->finally !== null) {
                $state->handlers[] = new Handler($handler->region, 'finally', $state->completion);
                $state->block = $handler->region->finally;
                $state->completion = new Completion();
                $result[] = $state;
                return $result;
            }
        }
        $result[] = $state;
        return $result;
    }

    /**
     * Checks source inheritance and the stable built-in throwable hierarchy.
     * @param Term $exception Exception identity
     * @param list<string> $types Catch types
     * @return bool Whether the known throwable matches a catch
     */
    public function matches(Term $exception, array $types): bool
    {
        $type = $exception->kind === 'object' ? ($exception->attributes['class'] ?? 'Throwable') : $exception->literal;
        if (!is_string($type)) {
            return in_array('throwable', array_map(strtolower(...), $types), true);
        }
        $seen = [];
        while ($type !== '' && !isset($seen[$type])) {
            if (in_array(strtolower($type), array_map(strtolower(...), $types), true) || in_array('throwable', array_map(strtolower(...), $types), true)) {
                return true;
            }
            $seen[$type] = true;
            $type = $this->program->classes()[strtolower($type)]->parent ?? $this->builtinParent($type);
        }
        return false;
    }

    /**
     * Describes target built-in exception inheritance without reflection.
     * @param string $type Throwable class
     * @return string Parent throwable class
     */
    public function builtinParent(string $type): string
    {
        return (new \Deriver\Internal\Solver\Call\Creation\Builtins())->parent($type);
    }

    /**
     * Makes a caught runtime error an ordinary object value with stable identity.
     * @param State $state Catch path memory
     * @param Term $exception Pending throwable or an already allocated exception
     * @return Term Inspectable object, preserving uncertainty about unknown throwable classes
     */
    public function capture(State $state, Term $exception): Term
    {
        if ($exception->kind !== 'throwable') {
            return $exception;
        }
        $id = $state->memory->fresh('caught-error');
        $class = is_string($exception->literal) ? $exception->literal : 'Throwable';
        $uncertain = ($exception->attributes['uncertain'] ?? false) === true;
        $object = new Term('object', $id, attributes: $uncertain ? ['type' => 'Throwable', 'uncertain' => true] : ['class' => $class]);
        $state->memory->cells['object:' . $id] = Term::array([], $uncertain);
        if (!$uncertain) {
            $state->memory->classes[$id] = $class;
            (new \Deriver\Internal\Solver\Call\Native\Properties($this->program))->initialize($class, $object, $state);
            $state->memory->write(new \Deriver\Internal\Memory\Location('object:' . $id, ['message']), Term::opaque('RUNTIME_DIAGNOSTIC', 'string'));
        }
        return $object;
    }
}
