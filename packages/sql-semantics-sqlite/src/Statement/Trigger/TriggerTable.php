<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\NamedRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;

/**
 * The table or view a trigger watches; the rows NEW and OLD refer to are rows of it.
 *
 * Rule: SQLITE-TRIGGER-TABLE-001. The name denotes a declared relation found
 * along the schema search path; the shape follows SQLITE-TABLE-SHAPE-001.
 * Source: https://sqlite.org/lang_createtrigger.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
