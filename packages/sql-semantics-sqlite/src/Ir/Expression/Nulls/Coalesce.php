<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Selects the first non-NULL value from an ordered SQLite scalar list.
 *
 * @visibility public
 * @example Retaining the first argument
 *     $first = new \SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument(new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral());
 *     $last = new \SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument(new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('7'));
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce([$first, $last]))->arguments[1]->expression->decimal // => '7'
 */
final class Coalesce
{
    use Snapshot;

    /**
     * @var non-empty-list<CoalesceArgument>
     */
    public readonly array $arguments;

    /**
     * Checks native arity and the ordered first non-NULL operand roles.
     *
     * @param list<CoalesceArgument> $arguments
     */
    public function __construct(array $arguments, public readonly bool $ifNull = false)
    {
        Check::input(!$ifNull || count($arguments) === 2, 'SQLite IFNULL requires exactly two arguments.');
        Check::input(count($arguments) <= 127, 'SQLite COALESCE allows at most 127 arguments.');
        $this->arguments = Check::listOf($arguments, CoalesceArgument::class, 'COALESCE requires at least two checked scalar arguments in order.', 2);
    }
}
