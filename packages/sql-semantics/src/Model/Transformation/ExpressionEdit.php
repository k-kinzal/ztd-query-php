<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transformation;

use ReflectionClass;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use UnitEnum;

/**

 * Rebuilds immutable constructor-owned operands, reapplying each concrete invariant. @visibility SqlSemantics

 */
final class ExpressionEdit
{
    /**
     * @template T of BoundStatement
     * @param T $statement
     * @return T
     * @throws InvalidStructure
     */
    public static function replace(BoundStatement $statement, Expression $target, Expression $replacement): BoundStatement
    {
        if (!in_array($target, \SqlSemantics\Model\Traversal\Expressions::all($statement), true)) {
            throw new InvalidStructure('The expression does not belong to this statement.');
        }
        return self::rebuild($statement, $target, $replacement, new RebuiltOperands());
    }

    /**
     * @template T of object
     * @param T $object
     * @return T
     * @throws InvalidStructure
     */
    public static function rebuild(object $object, Expression $target, Expression $replacement, ?RebuiltOperands $rebuilt = null): object
    {
        $rebuilt ??= new RebuiltOperands();
        $class = $object::class;
        $cached = $rebuilt->find($object);
        if ($cached !== null) {
            return $cached;
        }
        $reflection = new ReflectionClass($object);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $object;
        }
        $properties = get_object_vars($object);
        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!array_key_exists($name, $properties)) {
                throw new InvalidStructure('An immutable semantic operand must be retained: ' . $object::class . '::' . $name);
            }
            $before = $properties[$name];
            $arguments[$name] = $before;
        }
        array_walk_recursive($arguments, static function (&$operand) use ($target, $replacement, $rebuilt): void {
            if ($operand === $target) {
                $operand = $replacement;
            } elseif (is_object($operand) && !$operand instanceof UnitEnum && (str_starts_with($operand::class, 'SqlSemantics\\Model\\') || str_starts_with($operand::class, 'SqlSemantics\\Schema\\') || str_starts_with($operand::class, 'SqlSemantics\\Type\\')) && !$operand instanceof \SqlSemantics\Model\Statement\Origin && !$operand instanceof \SqlSemantics\Model\ColumnBinding && !$operand instanceof \SqlSemantics\Model\Scalar\ExpressionFacts) {
                $operand = self::rebuild($operand, $target, $replacement, $rebuilt);
            }
        });
        $changed = $arguments !== array_intersect_key($properties, $arguments);
        if (!$changed) {
            return $object;
        }
        $class = $object::class;
        return $rebuilt->remember($object, new $class(...$arguments));
    }

}
