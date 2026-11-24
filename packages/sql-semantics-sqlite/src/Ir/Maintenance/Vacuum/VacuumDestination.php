<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum;

use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One evaluated SQLite VACUUM INTO filename expression.
 *
 * @visibility public
 * @example Using a numbered filename parameter
 *     $parameter = new \SqlSemantics\Platform\Sqlite\Ir\Parameter('02');
 *     $operand = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow([$parameter]);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumDestination($operand))->toString() // => '(?02)'
 */
final class VacuumDestination
{
    use Snapshot;

    /**
     * Accepts a single checked operand independent of tables.
     * @throws InvalidConstruction When the checked operand list has more than one value
     */
    public function __construct(public readonly ValueRow $expression)
    {
        Check::input(count($expression->values) === 1, 'VACUUM INTO needs exactly one source-free filename operand.');
    }

    /**
     * Emits the evaluated filename expression.
     */
    public function toString(): string
    {
        return $this->expression->toString();
    }
}
