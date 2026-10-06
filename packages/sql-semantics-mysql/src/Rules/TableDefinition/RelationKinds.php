<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;

/**
 * Decides what a statement does with a name by the kind of relation its declaration is.
 *
 * Rule: MYSQL-RELATION-KIND-001. A CREATE VIEW declares a view; every other
 * declaration MySQL provides is a base table. Where a statement needs a base
 * table (ALTER TABLE, CREATE INDEX, CREATE TRIGGER, CREATE TABLE ... LIKE,
 * HANDLER ... OPEN) a declared view is refused with ER_WRONG_OBJECT ("is
 * not BASE TABLE"); where it needs a view (ALTER VIEW, CREATE OR REPLACE
 * VIEW, SHOW CREATE VIEW, DROP VIEW) a declared base table is refused with
 * ER_WRONG_OBJECT ("is not VIEW"). DROP TABLE does not find a view
 * (ER_BAD_TABLE_ERROR, a note under IF EXISTS), nor does TRUNCATE TABLE
 * (ER_NO_SUCH_TABLE). DROP VIEW IF EXISTS of a base table is refused in
 * 8.1, 8.2, 8.3, 9.0 and 9.1 and only noted in the other releases. A
 * declaration of a kind MySQL does not have (a materialized view, a foreign
 * table, a sequence) decides nothing. Verified on live servers of each
 * release. Source: https://dev.mysql.com/doc/refman/8.4/en/drop-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RelationKinds
{
    /**
     * Answers the kind a resolution decides: BaseTable or View for a declaration of that kind, else null.
     */
    public function kind(?TableResolution $resolution): ?RelationKind
    {
        if (!$resolution instanceof DeclaredTable) {
            return null;
        }

        return in_array($resolution->table->kind, [RelationKind::BaseTable, RelationKind::View], true) ? $resolution->table->kind : null;
    }

    /**
     * Reports a refusal when a name the statement needs as one kind resolves to a declaration of the other.
     *
     * @param RelationKind $needed BaseTable or View
     */
    public function require(Derivation $derivation, QualifiedName $name, ?TableResolution $resolution, RelationKind $needed): void
    {
        $kind = $this->kind($resolution);
        if ($kind !== null && $kind !== $needed) {
            $derivation->report(new WrongRelationKind($name, $needed === RelationKind::View ? KindRefusal::NotView : KindRefusal::NotBaseTable));
        }
    }

    /**
     * Reports a refusal when a name resolves to a declared view, with what the statement reports.
     */
    public function refuseView(Derivation $derivation, QualifiedName $name, ?TableResolution $resolution, KindRefusal $refusal): void
    {
        if ($this->kind($resolution) === RelationKind::View) {
            $derivation->report(new WrongRelationKind($name, $refusal));
        }
    }
}
