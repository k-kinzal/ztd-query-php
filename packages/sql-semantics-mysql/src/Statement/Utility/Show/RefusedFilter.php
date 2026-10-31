<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A refused scalar filter together with the inspection that owns it.
 *
 * The header retains its database and output-column scope. It has no filter
 * of its own; the refused operand is not published as an ordinary ShowWhere.
 *
 * @visibility public
 * @example Retaining the inspected database before filter preparation
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus(new \SqlSemantics\Statement\Identifier\Name('d')), new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row));
 *     $problem->header->database?->value // => 'd'
 */
final class RefusedFilter implements Diagnostic
{
    use Snapshot;

    /**
     * @param ShowTables|ShowTableStatus|ShowTriggers|ShowColumns|ShowKeys|ShowEvents|ShowProcedureStatus|ShowFunctionStatus|ShowDatabases|ShowOpenTables|ShowStatus|ShowVariables|ShowCharacterSet|ShowCollation $header The inspection without a filter
     * @param RefusedScalar $condition The non-scalar operand in the WHERE position
     */
    public function __construct(public readonly ShowTables|ShowTableStatus|ShowTriggers|ShowColumns|ShowKeys|ShowEvents|ShowProcedureStatus|ShowFunctionStatus|ShowDatabases|ShowOpenTables|ShowStatus|ShowVariables|ShowCharacterSet|ShowCollation $header, public readonly RefusedScalar $condition)
    {
        Check::input($header->filter === null, 'A refused inspection header has no second filter.');
    }

    /**
     * Describes the intrinsic filter problem; header conditions can precede it.
     */
    public function message(): string
    {
        return $this->condition->message();
    }
}
