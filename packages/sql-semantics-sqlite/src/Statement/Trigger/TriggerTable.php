<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * The table or view a trigger watches; the rows NEW and OLD refer to are rows of it.
 *
 * Rule: SQLITE-TRIGGER-TABLE-001. The name denotes a declared relation found
 * along the schema search path; the shape follows SQLITE-TABLE-SHAPE-001.
 * Source: https://sqlite.org/lang_createtrigger.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the table of a trigger
 *     $trigger = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TRIGGER r AFTER DELETE ON main.t BEGIN SELECT 1; END');
 *     [$trigger->statement->table->name()->schema->value, $trigger->statement->table->name()->name->value] // => ['main', 't']
 */
final class TriggerTable implements NamedRelation
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Answers the table name.
     */
    public function name(): QualifiedName
    {
        return $this->name;
    }

    /**
     * Answers no correlation name: the rows are reached as NEW and OLD.
     */
    public function alias(): ?Name
    {
        return null;
    }

    /**
     * Resolves the name among the declared relations and derives the row shape of the table.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableShapes())->fact($derivation, $this->name, $derivation->environment());
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
    }
}
