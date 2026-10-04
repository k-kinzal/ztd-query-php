<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Trigger;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * The table a trigger is created on.
 *
 * Rule: MYSQL-TRIGGER-TABLE-001. The name resolves as a table name of the
 * context (MYSQL-TABLE-SHAPES-001); its row is what NEW and OLD denote in
 * the trigger body (MYSQL-TRIGGER-ROWS-001). Terminates: one lookup.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the table of a trigger
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TRIGGER tr AFTER DELETE ON shop.t FOR EACH ROW SET @a = 1');
 *     [$create->statement->table->name->schema->value, $create->statement->table->name->name->value] // => ['shop', 't']
 */
final class TriggerTable implements Relation
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name with its optional database
     */
    public function __construct(public readonly QualifiedName $name)
    {
        Check::input($name->catalog === null, 'A table name has at most a database qualifier.');
    }

    /**
     * Resolves the table and answers its row.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableShapes())->named($this->name, $derivation, $environment);
    }

    /**
     * Writes the table name.
     */
    public function render(Output $out): void
    {
        (new ProgramNames())->qualified($out, $this->name, NameUse::Relation);
    }
}
