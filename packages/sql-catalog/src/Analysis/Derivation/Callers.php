<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\SinkMatcher;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Php\ProgramIndex;

/**
 * The calls in the source that reach a given function or method.
 *
 * A function is reached by the calls written with its name. A method is
 * reached by the calls written with its name on something that can be an
 * instance of its class; what they are written on is worked out the same way
 * any other value is, so `$repository->find(…)` is a caller of
 * `Repository::find` when `$repository` can be a `Repository`. A call on
 * something whose class could not be worked out is not taken as a caller, since
 * a method of the same name on a built-in class or on a class outside the
 * analyzed files may be the one it reaches; the callers found are then marked
 * as partial instead.
 *
 * A call written on a class name reaches the method that class has, declared or
 * inherited: `parent::` looks from the parent of the class it is written in and
 * `self::` from that class itself. Only `static::` and a call on an instance
 * can also land in a subclass that overrides the method.
 *
 * A method of a class an extension models has no callers here. The extension
 * already says what calling it does, and every call of it is read where it is
 * written; reading it again from inside the class, once per caller, would list
 * each of those statements a second time under the class's own line.
 *
 * @visibility root
 */
final class Callers
{
    private CallerIndex $calls;

    private ProgramIndex $index;

    private ?SinkMatcher $sinks;

    /**
     * Wires the lookup to the calls and the declarations it filters them with.
     */
    public function __construct(CallerIndex $calls, ProgramIndex $index, ?SinkMatcher $sinks = null)
    {
        $this->calls = $calls;
        $this->index = $index;
        $this->sinks = $sinks;
    }

    /**
     * The calls that reach a body, in the order they are written.
     */
    public function of(FunctionLike $body, int $depth, Deriver $deriver): CallerSet
    {
        if ($body instanceof Stmt\Function_) {
            return new CallerSet($this->ofFunction($body));
        }
        if (!$body instanceof Stmt\ClassMethod) {
            return new CallerSet([]);
        }
        $className = $deriver->classOf($body);
        if ($className === null || $this->sinks?->models(Domain::of(new ObjectTerm($className))) === true) {
            return new CallerSet([]);
        }
        $method = $body->name->toString();
        $callers = strtolower($method) === '__construct' ? $this->instantiations($className, $deriver) : [];
        $partial = false;
        foreach ($this->calls->named($method) as $call) {
            $reaches = $this->reaches($call, $className, $depth, $deriver);
            if ($reaches === true) {
                $callers[] = $call;
            }
            $partial = $partial || $reaches === null;
        }

        return new CallerSet($callers, $partial);
    }

    /**
     * The calls written with a function's fully qualified name.
     *
     * @return list<Expr\CallLike>
     */
    public function ofFunction(Stmt\Function_ $function): array
    {
        $wanted = strtolower($function->namespacedName?->toString() ?? $function->name->toString());
        $callers = [];
        foreach ($this->calls->named($function->name->toString()) as $call) {
            if (!$call instanceof Expr\FuncCall || !$call->name instanceof Node\Name) {
                continue;
            }
            $namespaced = $call->name->getAttribute('namespacedName');
            $candidates = [strtolower(ltrim($call->name->toString(), '\\'))];
            if ($namespaced instanceof Node\Name) {
                $candidates[] = strtolower($namespaced->toString());
            }
            if (in_array($wanted, $candidates, true)) {
                $callers[] = $call;
            }
        }

        return $callers;
    }

    /**
     * The instantiations that run a class's constructor.
     *
     * @return list<Expr\CallLike>
     */
    public function instantiations(string $className, Deriver $deriver): array
    {
        $callers = [];
        foreach ($this->inheritorsOf($className) as $constructed) {
            foreach ($this->calls->instantiating($constructed) as $new) {
                $written = $new->class instanceof Node\Name ? $new->class->toString() : null;
                $resolved = $written !== null && in_array(strtolower($written), ['self', 'static'], true)
                    ? $deriver->classOf($new)
                    : $written;
                if ($resolved !== null && strcasecmp(ltrim($resolved, '\\'), ltrim($constructed, '\\')) === 0) {
                    $callers[] = $new;
                }
            }
        }

        return $callers;
    }

    /**
     * The classes whose instantiation runs a class's constructor: the class, and every subclass that inherits the constructor rather than declaring its own.
     *
     * @return list<string>
     */
    public function inheritorsOf(string $className): array
    {
        $wanted = strtolower(ltrim($className, '\\'));
        $classes = [$className];
        foreach ($this->index->classes as $key => $shape) {
            $current = $key === $wanted ? null : $shape;
            $seen = [];
            while ($current !== null && !isset($current->methods['__construct']) && !isset($seen[$current->name])) {
                $seen[$current->name] = true;
                $current = $this->index->findClass($current->parent);
            }
            if ($current !== null && strtolower(ltrim($current->name, '\\')) === $wanted) {
                $classes[] = $shape->name;
            }
        }

        return $classes;
    }

    /**
     * Whether a call written with the method's name can reach the method of that class, or null when that cannot be told.
     */
    public function reaches(Expr\CallLike $call, string $className, int $depth, Deriver $deriver): ?bool
    {
        $method = $this->calls->nameOf($call) ?? '';
        if ($call instanceof Expr\StaticCall) {
            if (!$call->class instanceof Node\Name) {
                return null;
            }
            $target = $this->staticTarget($call, $deriver);
            if ($target === null) {
                return false;
            }
            $late = strtolower($call->class->toString()) === 'static';

            return $late ? $this->related($target, $className, $method) : $this->index->isInstanceOf($target, $className);
        }
        if (!$call instanceof Expr\MethodCall && !$call instanceof Expr\NullsafeMethodCall) {
            return false;
        }
        if ($call->var instanceof Expr\Variable && $call->var->name === FreeNames::THIS) {
            $enclosing = $deriver->classOf($call);

            return $enclosing !== null && $this->related($enclosing, $className, $method);
        }
        $classes = [];
        foreach ($deriver->solveUnlessSpent($call, [$call->var], $depth + 1) as $solution) {
            foreach ($solution->values[0]->type()->classNames() as $candidate) {
                $classes[] = $candidate;
            }
        }
        if ($classes === []) {
            return null;
        }
        foreach ($classes as $candidate) {
            if ($this->related($candidate, $className, $method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The class a call written on a class name looks its method up from, or null when it cannot be named.
     */
    public function staticTarget(Expr\StaticCall $call, Deriver $deriver): ?string
    {
        if (!$call->class instanceof Node\Name) {
            return null;
        }
        $written = $call->class->toString();

        return match (strtolower($written)) {
            'self', 'static' => $deriver->classOf($call),
            'parent' => $this->index->findClass($deriver->classOf($call) ?? '')?->parent,
            default => $written,
        };
    }

    /**
     * Whether a call of the method on an instance of one class can land in the method as the other class declares it.
     *
     * The method is looked up from the class of the instance upwards, and the
     * first class that declares it is the one that runs. So a call on an
     * instance of a subclass lands in the declaring class's method only when
     * nothing between them overrides it, and a call on an instance of a parent
     * class can land in the declaring class's method when the instance is of
     * that subclass.
     */
    public function related(string $receiver, string $declaring, string $method): bool
    {
        if ($this->index->isInstanceOf($declaring, $receiver)) {
            return true;
        }
        if (!$this->index->isInstanceOf($receiver, $declaring)) {
            return false;
        }
        $key = strtolower($method);
        $wanted = strtolower(ltrim($declaring, '\\'));
        $current = $this->index->findClass($receiver);
        $seen = [];
        while ($current !== null && strtolower($current->name) !== $wanted && !isset($seen[$current->name])) {
            if (isset($current->methods[$key])) {
                return false;
            }
            $seen[$current->name] = true;
            $current = $this->index->findClass($current->parent);
        }

        return true;
    }
}
