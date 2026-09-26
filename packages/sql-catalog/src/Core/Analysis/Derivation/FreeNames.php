<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\ExternalInput;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;

/**
 * The names an expression reads, which are what its value depends on.
 *
 * A variable is named as itself; a property of the object a method runs on is
 * named `this->property`, because an assignment to it earlier in the body is a
 * definition in the same way an assignment to a variable is; the object itself
 * is named `this`. Superglobals are not named: nothing in the source defines
 * them, so there is nothing to look for.
 *
 * A database call written inside the expression reads less than it passes.
 * What a preparing or composing call hands back depends on the statement text
 * alone, and what a query hands back does not depend on its arguments at all,
 * so the values it binds are not looked for. Without this, a statement built
 * from `$wpdb->prepare('(%s, %s)', $option, $value)` would chase every value it
 * binds through every function that computes one, and none of that can change
 * the text.
 *
 * @visibility root
 */
final class FreeNames
{
    /**
     * The name a property of `$this` is tracked under.
     */
    public const THIS = 'this';

    private ExternalInput $external;

    /**
     * @var array<string, array<int, true>>
     */
    private array $sinkArguments = [];

    private bool $trackObjectEffects = false;

    /**
     * Builds the reader over the database calls whose arguments it reads selectively.
     *
     * @param list<SinkSpec> $sinks
     */
    public function __construct(array $sinks = [], ?ExternalInput $external = null, bool $trackObjectEffects = false)
    {
        $this->external = $external ?? new ExternalInput();
        $this->trackObjectEffects = $trackObjectEffects;
        foreach ($sinks as $sink) {
            $this->trackObjectEffects = $this->trackObjectEffects || $sink->role === SinkRole::Modelled;
            $name = strtolower(ltrim($sink->name, '\\'));
            $positions = $this->sinkArguments[$name] ?? [];
            if (($sink->role === SinkRole::Compose || $sink->role === SinkRole::Prepare) && $sink->sqlParameter !== null) {
                $positions[$sink->sqlParameter] = true;
            }
            $this->sinkArguments[$name] = $positions;
        }
    }

    /**
     * The names read by any of the given expressions.
     *
     * @param list<Node> $nodes
     * @return array<string, true>
     */
    public function of(array $nodes): array
    {
        $names = [];
        foreach ($nodes as $node) {
            $names += $this->read($node);
        }

        return $names;
    }

    /**
     * The names one node reads.
     *
     * @return array<string, true>
     */
    public function read(Node $node): array
    {
        if ($node instanceof Expr\Variable) {
            return $this->variable($node);
        }
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignRef) {
            return $this->read($node->expr) + $this->targetReads($node->var);
        }
        $property = $this->propertyName($node);
        if ($property !== null) {
            return [$property => true];
        }
        if ($node instanceof Expr\Closure) {
            $names = [];
            foreach ($node->uses as $use) {
                $names += $this->variable($use->var);
            }

            return $names;
        }
        if ($node instanceof Expr\ArrowFunction) {
            return $this->arrowFunction($node);
        }
        if ($node instanceof Expr\CallLike) {
            $positions = $this->sinkArgumentsOf($node);
            if ($positions !== null) {
                return $this->sinkCall($node, $positions);
            }
        }

        $names = [];
        foreach (get_object_vars($node) as $sub) {
            foreach (is_array($sub) ? $sub : [$sub] as $child) {
                if ($child instanceof Node) {
                    $names += $this->read($child);
                }
            }
        }

        return $names;
    }

    /**
     * The argument positions whose values decide what a database call hands back, or null when the call is not written as one.
     *
     * @return array<int, true>|null
     */
    public function sinkArgumentsOf(Expr\CallLike $call): ?array
    {
        if ($this->trackObjectEffects) {
            return null;
        }
        $name = null;
        if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall || $call instanceof Expr\StaticCall) {
            $name = $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        }
        if ($call instanceof Expr\FuncCall && $call->name instanceof Node\Name) {
            $name = $call->name->toString();
        }

        return $name === null ? null : ($this->sinkArguments[strtolower(ltrim($name, '\\'))] ?? null);
    }

    /**
     * The names a database call reads: what it is called on, and the arguments its result depends on.
     *
     * @param array<int, true> $positions
     * @return array<string, true>
     */
    public function sinkCall(Expr\CallLike $call, array $positions): array
    {
        $names = $call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall ? $this->read($call->var) : [];
        if ($call->isFirstClassCallable()) {
            return $names;
        }
        foreach ($call->getArgs() as $position => $argument) {
            if (isset($positions[$position]) || $argument->unpack) {
                $names += $this->read($argument->value);
            }
        }

        return $names;
    }

    /**
     * The names an assignment target reads without being what it writes.
     *
     * Writing `$rows[$key]` reads `$key`, and reads `$rows` too, since the
     * element is added to whatever the array already held. Writing `$sql` reads
     * nothing: the old value is simply replaced.
     *
     * @return array<string, true>
     */
    public function targetReads(Node $target): array
    {
        if ($this->trackObjectEffects && $target instanceof Expr\PropertyFetch) {
            return $this->read($target->var);
        }
        if ($target instanceof Expr\ArrayDimFetch) {
            return $this->read($target);
        }
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            $names = [];
            foreach ($target->items as $item) {
                if ($item !== null) {
                    $names += ($item->key === null ? [] : $this->read($item->key)) + $this->targetReads($item->value);
                }
            }

            return $names;
        }

        return [];
    }

    /**
     * The name a variable is read under, or nothing when it is external or not a plain name.
     *
     * @return array<string, true>
     */
    public function variable(Expr\Variable $node): array
    {
        if (!is_string($node->name) || $this->external->isVariable($node->name)) {
            return [];
        }

        return [$node->name => true];
    }

    /**
     * The tracked name of a property of `$this`, or null when the node is not one.
     */
    public function propertyName(Node $node): ?string
    {
        if (!$node instanceof Expr\PropertyFetch && !$node instanceof Expr\NullsafePropertyFetch) {
            return null;
        }
        if (!$node->var instanceof Expr\Variable || $node->var->name !== self::THIS) {
            return null;
        }

        return $node->name instanceof Node\Identifier ? self::THIS . '->' . $node->name->toString() : null;
    }

    /**
     * The names an arrow function captures from the scope it is written in.
     *
     * @return array<string, true>
     */
    public function arrowFunction(Expr\ArrowFunction $node): array
    {
        $names = $this->read($node->expr);
        foreach ($node->params as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                unset($names[$parameter->var->name]);
            }
        }

        return $names;
    }
}
