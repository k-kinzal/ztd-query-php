<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;

/**
 * A request to create an empty table with the definition of another table: CREATE TABLE ... LIKE.
 *
 * Rule: MYSQL-CREATE-TABLE-LIKE-001. The source table is a table use: the
 * statement node records its resolution as a target; a declared view is
 * refused (MYSQL-RELATION-KIND-001). The statement provides
 * one declaration with new columns of the same names, types and NULL facts
 * as the source (MYSQL-TABLE-DECLARATION-001); an undeclared or missing
 * source leaves it empty and incomplete. `LIKE t` and `(LIKE t)` are the same
 * request. Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-like.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateTableLike implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name
     * @param QualifiedName $source The table whose definition is copied
     * @param int $temporaryWords How many times TEMPORARY is written; MySQL 8.0 and later accept it once
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly QualifiedName $source,
        public readonly int $temporaryWords = 0,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null && $source->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($temporaryWords >= 0, 'TEMPORARY is written a non-negative number of times.');
    }

    /**
     * Resolves the source table and provides the copied declaration.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new TableTargets())->existing($derivation, $this->source));
        (new RelationKinds())->require($derivation, $this->source, $fact->table, RelationKind::BaseTable);
        $derivation->declare((new TableDeclaration())->like(
            $this->name,
            $fact->table instanceof DeclaredTable ? $fact->table->table : null,
            $derivation->context->profile,
            $this->temporaryWords > 0,
        ));
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        for ($word = 0; $word < $this->temporaryWords; $word++) {
            $out->keyword('TEMPORARY');
        }
        $out->keyword('TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        foreach ([$this->name, $this->source] as $position => $name) {
            if ($position === 1) {
                $out->keyword('LIKE');
            }
            if ($name->schema !== null) {
                $out->name($name->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($name->name, NameUse::Relation);
        }
    }
}
