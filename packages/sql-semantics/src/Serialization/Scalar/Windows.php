<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Window;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes frame units, bounds and exclusions as distinct semantic components. @visibility SqlSemantics

 */
final class Windows
{
    /**
     * @throws InvalidStructure
     */
    public static function write(Window\Window $window, Dialect $dialect): Tree
    {
        if ($window instanceof Window\NamedWindow) {
            return Build::identifier([$window->name], $dialect);
        }
        if (!$window instanceof Window\WindowSpecification) {
            throw new InvalidStructure('Unclassified window: ' . $window::class);
        }
        $frame = $window->frame;
        return Build::parentheses(new Tree('window', [...($window->base === null ? [] : [Build::identifier([$window->base], $dialect)]), Parts::expressions('PARTITION BY', $window->partitionBy), Parts::ordering($window->orderBy), ...($frame === null ? [] : [Build::keyword($frame->unit->value), Build::keyword('BETWEEN'), self::boundary($frame->start), Build::keyword('AND'), self::boundary($frame->end), ...($frame->exclusion === Window\FrameExclusion::None ? [] : [Build::keyword('EXCLUDE ' . $frame->exclusion->value)])])]));
    }

    /**
     * @throws InvalidStructure
     */
    public static function boundary(Window\Boundary $bound): Tree
    {
        return match (true) {
            $bound instanceof Window\CurrentRow => Build::keyword('CURRENT ROW'),
            $bound instanceof Window\Unbounded => Build::keyword('UNBOUNDED ' . $bound->direction->value),
            $bound instanceof Window\Offset => new Tree('offset', [...($bound->unit === null ? [Expressions::write($bound->value)] : [Build::keyword('INTERVAL'), Expressions::write($bound->value), Build::keyword($bound->unit->value)]), Build::keyword($bound->direction->value)]),
            default => throw new InvalidStructure('Unclassified window boundary: ' . $bound::class),
        };
    }
}
