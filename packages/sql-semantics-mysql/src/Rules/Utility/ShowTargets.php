<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Resolves the table a SHOW or DESCRIBE reports on.
 *
 * Rule: MYSQL-SHOW-TARGET-001. The name resolves by CORE-TABLE-LOOKUP-001
 * in the database written after FROM or IN when there is one (it replaces a
 * database written before the table name), else in the database written
 * with the name, else in the current database; common table expressions
 * are not visible. The fact of the InspectedTable node is that resolution
 * with the shape of the table: one slot per declared column, an open shape
 * for an undeclared table or an incomplete column list, an empty shape for
 * a missing or conflicting name, which is a diagnostic (the server reports
 * ER_NO_SUCH_TABLE). Terminates: one pass over the columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-columns.html
 * ("SHOW COLUMNS FROM mytable FROM mydb" equals "SHOW COLUMNS FROM
 * mydb.mytable"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ShowTargets
{
    /**
     * Resolves the inspected table, records its fact and answers it.
     */
    public function derive(Derivation $derivation, InspectedTable $table, ?Name $database = null): RelationFact
    {
        $name = $database === null ? $table->name : new QualifiedName($table->name->name, $database);
        $resolution = $derivation->table($name, $derivation->environment());
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return $derivation->target($table, new RelationFact(new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]), $resolution));
        }
        $missing = $resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable ? [$resolution->missing] : [];

        return $derivation->target($table, new RelationFact(new RowShape([], $missing), $resolution));
    }
}
