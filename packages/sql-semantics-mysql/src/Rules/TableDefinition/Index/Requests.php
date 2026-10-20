<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Binds and renders complete index requests independently of their ordinary or retained role.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Requests
{
    /**
     * Binds the target, key operands and alteration modifiers in their declared scope.
     */
    public function derive(CreateIndex|DeferredCreateIndex $statement, Derivation $derivation): void
    {
        $problem = (new Names())->problem($statement->name);
        if ($problem !== null) {
            $derivation->report($problem);
        }
        $shape = (new Shapes())->problem($statement->kind, $statement->parts);
        if ($shape !== null) {
            $derivation->report($shape);
        }
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\IndexOptions())->parsers($statement->options, $derivation);
        $targets = new TableTargets();
        $fact = $derivation->relation($statement, $derivation->environment());
        (new RelationKinds())->require($derivation, $statement->table, $fact->table, RelationKind::BaseTable);
        $declared = $fact->table instanceof DeclaredTable ? $fact->table->table : null;
        $scope = $targets->scope($derivation, $statement, new QualifiedName($statement->table->name), $fact->shape, $declared === null ? [] : $targets->implicit($declared));
        foreach ($statement->parts as $part) {
            $part->deriveKeyPart($derivation, $scope);
            if ($declared !== null && $declared->complete && $part instanceof ColumnPart && $declared->matchingColumns($part->column->value, $derivation->context->columnNames) === []) {
                $derivation->report(new UnknownKeyColumn($part->column));
            }
        }
        foreach ($statement->alterOptions as $option) {
            $option->deriveOption($derivation);
        }
    }

    /**
     * Writes every retained index operand without recovering input syntax.
     */
    public function render(CreateIndex|DeferredCreateIndex $statement, Output $out): void
    {
        $out->keyword('CREATE');
        if ($statement->kind !== IndexKind::Index) {
            $out->keyword($statement->kind->value);
        }
        $out->keyword('INDEX')->name($statement->name, NameUse::Label);
        if ($statement->algorithm !== null) {
            $out->keyword('USING', $statement->algorithm->value);
        }
        $out->keyword('ON');
        if ($statement->table->schema !== null) {
            $out->name($statement->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($statement->table->name, NameUse::Relation)->symbol('(')->list($statement->parts)->symbol(')');
        foreach ([...$statement->options, ...$statement->alterOptions] as $option) {
            $out->node($option);
        }
    }
}
