<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Effect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\Origin;

/**
 * Writes whose values cannot be reconstructed, including writes hidden behind calls.
 *
 * A wildcard means code can modify the current symbol table. Reference aliases
 * are treated conservatively, never as evidence that a local is undefined.
 *
 * @visibility root
 */
final class WriteEffects
{
    /**
     * Every local may be changed.
     */
    public const ALL = '*';

    private ?ReferenceEffects $references = null;

    /**
     * The names an operation may change without an ordinary assignment.
     *
     * @return array<string, true>
     */
    public function own(Node $node, ?ProgramIndex $index = null): array
    {
        if ($node instanceof Expr\Include_ || $node instanceof Expr\Eval_) {
            return [self::ALL => true];
        }
        $names = new ModifiedNames();
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
            $dynamic = $this->dynamicTarget($node->var);
            if ($dynamic !== []) {
                return $dynamic;
            }
        }
        if ($node instanceof Expr\AssignRef) {
            return $names->targets($node->var) + $names->targets($node->expr);
        }
        if (!$node instanceof Expr\CallLike || $node->isFirstClassCallable()) {
            return [];
        }
        if ($node instanceof Expr\FuncCall && (!$node->name instanceof Node\Name
            || in_array(strtolower($node->name->getLast()), ['extract', 'parse_str', 'mb_parse_str'], true))) {
            return [self::ALL => true];
        }

        return $this->arguments($node, $index);
    }

    /**
     * Invalidates possible aliases before the direct target receives its new value.
     */
    public function assignment(Expr $target, Environment $environment): void
    {
        $written = (new ModifiedNames())->targets($target);
        $this->references ??= new ReferenceEffects();
        $this->apply($this->references->affected($target, $written) + $this->dynamicTarget($target), $environment);
    }

    /**
     * Dynamic assignment targets can address any binding in the current scope.
     *
     * @return array<string, true>
     */
    public function dynamicTarget(Expr $target): array
    {
        if ($target instanceof Expr\ArrayDimFetch) {
            return $this->dynamicTarget($target->var);
        }
        if ($target instanceof Expr\Variable && !is_string($target->name)) {
            return [self::ALL => true];
        }
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            foreach ($target->items as $item) {
                if ($item !== null && $this->dynamicTarget($item->value) !== []) {
                    return [self::ALL => true];
                }
            }
        }

        return [];
    }

    /**
     * Writable arguments stay open unless a source declaration excludes reference passing.
     *
     * @return array<string, true>
     */
    public function arguments(Expr\CallLike $call, ?ProgramIndex $index = null): array
    {
        $parameters = $call instanceof Expr\FuncCall && $call->name instanceof Node\Name
            ? $index?->findFunction($call->name->toString())?->node?->getParams() : null;
        $names = new ModifiedNames();
        $written = [];
        foreach ($call->getArgs() as $position => $argument) {
            $parameter = $argument->name === null ? ($parameters[$position] ?? null) : null;
            foreach ($parameters ?? [] as $candidate) {
                if ($argument->name !== null && $candidate->var instanceof Expr\Variable
                    && $candidate->var->name === $argument->name->toString()) {
                    $parameter = $candidate;
                }
            }
            if ($parameter !== null && !$parameter->byRef && !$argument->unpack) {
                continue;
            }
            $written += $names->targets($argument->value) + $this->dynamicTarget($argument->value);
        }

        return $written;
    }

    /**
     * Opens affected bindings after an operation; later definite assignments may close them again.
     *
     * @param array<string, true> $names
     */
    public function apply(array $names, Environment $environment, Origin $origin = Origin::Unresolved): void
    {
        if (isset($names[self::ALL])) {
            $names = array_fill_keys($environment->names(), true);
        }
        foreach ($names as $name => $_) {
            $environment->invalidate($name, $origin);
        }
    }
}
