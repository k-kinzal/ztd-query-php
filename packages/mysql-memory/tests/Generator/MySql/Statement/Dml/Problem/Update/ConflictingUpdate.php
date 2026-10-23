<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Update;

use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Generates distinct table occurrences with the same name for the collision diagnostic.
 */
final class ConflictingUpdate implements Inhabitant
{
    /**
     * Keeps the repeated name while varying update flags, target column and value.
     */
    public function sample(Choices $choices): Model
    {
        $name = $choices->one(['t', 'table name', '表']);
        $tables = [new TableReference(new QualifiedName(new Name($name))), new TableReference(new QualifiedName(new Name($name)))];
        $assignment = new Assignment(new ColumnUse(new Name($choices->one(['a', 'b']))), new NumberLiteral((string) $choices->integer(0, 20)));
        return new Model(null, $choices->integer(0, 1) === 1, $choices->integer(0, 1) === 1, $tables, [$assignment]);
    }
}
