<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;

/**
 * Resolves the declared coordinate and local restrictions of an ALTER TABLE removal.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropColumnLowering
{
    /**
     * Exports only the closed member heading and the absolute target.
     *
     * @throws InvalidConstruction When the target or declaration-local facts are unavailable
     */
    public function lower(BoundStatement $operation, AlterDropColumn $statement): DropColumn
    {
        [$schema, $name, $table] = (new AlterTargetLowering())->resolve($operation, $statement);
        $restrictions = $table->columnRemoval;
        Check::input($restrictions !== null, 'A column removal requires resolved declaration-local restrictions.');
        $wanted = new Identifier($statement->column->value);
        $heading = [];
        $selected = null;
        foreach ($table->columns as $ordinal => $column) {
            $identity = new Identifier($column->name->value);
            $heading[] = new DropMember($identity, $column->generated, isset($restrictions->protected[$ordinal]));
            if ($identity->key() === $wanted->key()) {
                $selected = $ordinal;
            }
        }
        Check::input($selected !== null, 'A column removal must select a declared member.');

        return new DropColumn($schema, $name, $heading, $selected);
    }
}
