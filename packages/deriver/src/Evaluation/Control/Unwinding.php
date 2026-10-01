<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Identity;
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
     * Resumes nonthrowing completions through finally blocks and jump destinations.
     * @param State $state Completing execution path
     * @return bool Whether the path resumes within this callable
     */
    public function resume(State $state): bool
    {
        while ($state->handlers !== [] && count($state->handlers) > $state->completion->depth) {
            $handler = array_pop($state->handlers);
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
        if ($state->completion->kind === 'exit') {
            return [$state];
        }
        if ($state->completion->kind !== 'throw') {
            $this->resume($state);
            return [$state];
        }
        $value = $state->completion->value ?? Term::opaque('EXCEPTION', 'Throwable');
        [$valid, $invalid] = (new ExceptionMatch($this->program))->partition($value, ['Throwable']);
        $result = [];
        if ($valid !== null) {
            $normal = $state->fork();
            $normal->completion = new Completion('throw', $valid);
            if ($invalid !== null) {
                $normal->guard['throwable:' . (new Identity())->key($value)] = true;
            }
            array_push($result, ...$this->throwRoutes($normal));
        }
        if ($invalid !== null) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error', secret: $value->isSecret()));
            if ($valid !== null) {
                $state->guard['throwable:' . (new Identity())->key($value)] = false;
            }
            array_push($result, ...$this->throwRoutes($state));
        }
        return $result;
    }

    /**
     * Applies catches in order and carries only the uncaught subset into finally.
     * @param State $state Valid pending throwable
     * @return list<State> Caught, finally, and escaping paths
     */
    public function throwRoutes(State $state): array
    {
        $result = [];
        while ($state->handlers !== [] && count($state->handlers) > $state->completion->depth) {
            $handler = array_pop($state->handlers);
            (new ExceptionChain($this->program))->unwind($state, $handler);
            if ($handler->phase === 'try') {
                foreach ($handler->region->catches as $catch) {
                    [$matched, $remaining] = (new ExceptionMatch($this->program))->partition($state->completion->value ?? Term::opaque('EXCEPTION', 'Throwable'), $catch->types);
                    $guard = 'catch:' . $handler->region->continuation . ':' . $catch->block;
                    if ($matched !== null) {
                        $caught = $state->fork();
                        $caught->handlers[] = new Handler($handler->region, 'catch');
                        if ($remaining !== null) {
                            $caught->guard[$guard] = true;
                        }
                        if ($catch->variable !== '') {
                            $caught->memory->write($caught->local($catch->variable), $this->capture($caught, $matched));
                        }
                        $caught->block = $catch->block;
                        $caught->completion = new Completion();
                        $result[] = $caught;
                    }
                    if ($remaining === null) {
                        return $result;
                    }
                    if ($matched !== null) {
                        $state->guard[$guard] = false;
                    }
                    $state->completion = new Completion('throw', $remaining);
                }
            }
            if ($handler->phase !== 'finally' && $handler->region->finally !== null) {
                $state->handlers[] = new Handler($handler->region, 'finally', $state->completion);
                $state->block = $handler->region->finally;
                $state->completion = new Completion();
                break;
            }
        }
        $result[] = $state;
        return $result;
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
        $object = new Term('object', $id, attributes: $uncertain ? ['type' => 'Throwable', 'uncertain' => true] : ['class' => $class], secret: $exception->isSecret());
        $state->memory->cells['object:' . $id] = Term::array([], $uncertain);
        if (!$uncertain) {
            $state->memory->classes[$id] = $class;
            (new Properties($this->program))->initialize($class, $object, $state);
            $state->memory->write(new Location('object:' . $id, ['message']), Term::opaque('RUNTIME_DIAGNOSTIC', 'string'));
        }
        return $object;
    }
}
