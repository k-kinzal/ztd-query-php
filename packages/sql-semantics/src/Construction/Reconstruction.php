<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use Closure;
use ReflectionClass;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;

/**
 * Reconstructs statement occurrences through their constructors, without SQL or parser nodes.
 *
 * Replacement expressions are reconstructed too, so two uses never share a scalar occurrence.
 * The new operation will derive fresh facts and validate the reconstructed structure.
 * Constructor operands must be retained under their parameter names. Values that are not
 * statement nodes remain shared immutable values; every node occurrence is reconstructed.
 *
 * @example Reconstructing a statement without reading SQL
 *     $original = new \SqlSemantics\Statement\Script([]);
 *     $copy = (new \SqlSemantics\Construction\Reconstruction())->node($original);
 *     [$copy == $original, $copy === $original] // => [true, false]
 *
 * @visibility public
 */
final class Reconstruction
{
    /**
     * @param Closure(Node): ?Node|null $replace Replaces a source occurrence, or returns null to retain its form
     */
    public function __construct(public readonly ?Closure $replace = null)
    {
    }

    /**
     * Reconstructs one node with optional explicitly supplied constructor operands.
     *
     * @param array<string, mixed> $changes
     */
    public function node(Node $node, array $changes = []): Node
    {
        $replacement = $this->replace === null ? null : ($this->replace)($node);
        if ($replacement !== null) {
            return (new self())->node($replacement);
        }
        $class = new ReflectionClass($node);
        $arguments = [];
        $parameters = $class->getConstructor()?->getParameters() ?? [];
        Check::input(array_diff(array_keys($changes), array_map(static fn ($parameter): string => $parameter->getName(), $parameters)) === [], 'Changed operands must name constructor parameters.');
        $values = get_object_vars($node);
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if (in_array($name, ['scalarInformation', 'relationInformation', 'queryInformation', 'statementInformation'], true)) {
                $arguments[] = null;
                continue;
            }
            Check::invariant(array_key_exists($name, $values), 'A structural rewrite needs the constructor operand ' . $class->getName() . '::$' . $name . '.');
            $arguments[] = $this->value(array_key_exists($name, $changes) ? $changes[$name] : $values[$name]);
        }

        return $class->newInstanceArgs($arguments);
    }

    /**
     * Reconstructs nested nodes and lists, retaining immutable non-node values.
     */
    public function value(mixed $value): mixed
    {
        if ($value instanceof Node) {
            return $this->node($value);
        }
        if (is_array($value)) {
            return array_map($this->value(...), $value);
        }

        return $value;
    }
}
