<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * An incompatible LOAD input with typed operands for preceding name resolution.
 *
 * No invalid statement, source SQL or parser node is retained. The table and
 * character set precede assignment interpretation, which precedes the conflict.
 *
 * @visibility public
 * @example Describing an input whose bulk options cannot coexist
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data, new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule::ColumnsBulk)))->message() // => 'Incorrect usage of LOAD DATA with BULK Algorithm and column list specification'
 */
final class RefusedLoadOptions implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<Assignment> The preceding assignments, in written order
     */
    public readonly array $assignments;

    /**
     * @param QualifiedName $table The table name and optional database
     * @param LoadFormat $format The prohibited instruction kind inside a program
     * @param LoadConflict $problem The first incompatible option combination
     * @param CharsetName|null $charset The preceding input character set
     * @param list<Assignment> $assignments The preceding assignment operands
     */
    public function __construct(public readonly QualifiedName $table, public readonly LoadFormat $format, public readonly LoadConflict $problem, public readonly ?CharsetName $charset = null, array $assignments = [])
    {
        $this->assignments = Check::listOf($assignments, Assignment::class, 'LOAD input assignments retain their written order.');
    }

    /**
     * Describes the incompatible combination without consulting input text.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
