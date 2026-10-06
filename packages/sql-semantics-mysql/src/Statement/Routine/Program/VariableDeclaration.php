<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * DECLARE of local variables: one or more names of one type with an optional default value.
 *
 * Rule: MYSQL-PROGRAM-VARIABLES-001. The declaration is the relation whose
 * row holds one position per declared name: a name used as a value in the
 * block resolves to that position. Every position has the declared type and
 * can be NULL, since a variable without DEFAULT starts as NULL and any
 * variable can be assigned NULL. The default value is derived in the scope
 * before the declaration. Terminates: one pass over the names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-local-variable.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the variables of a declaration
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE a, b INT DEFAULT 0; END');
 *     $declaration = $create->statement->body->declarations[0];
 *     [count($declaration->names), $declaration->type->name(), $declaration->default !== null] // => [2, 'INT', true]
 */
final class VariableDeclaration implements Declaration, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The variable names in written order
     */
    public readonly array $names;

    /**
     * @param list<Name> $names The variable names; at least one
     * @param TypeName $type The declared type
     * @param CollationName|null $collation The COLLATE clause of the type
     * @param Scalar|null $default The DEFAULT value
     */
    public function __construct(array $names, public readonly TypeName $type, public readonly ?CollationName $collation = null, public readonly ?Scalar $default = null)
    {
        $this->names = Check::listOf($names, Name::class, 'DECLARE names at least one variable.', 1);
    }

    /**
     * Derives the default value and answers the row of the variables.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        if ($this->default !== null) {
            $derivation->scalar($this->default, $environment);
        }
        $slots = [];
        foreach ($this->names as $name) {
            $slots[] = new OutputSlot($name, new Known($this->type), Nullability::Nullable);
        }

        return new RelationFact(new RowShape($slots));
    }

    /**
     * Writes the declaration.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECLARE');
        foreach ($this->names as $position => $name) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($name, NameUse::Identifier);
        }
        (new ProgramNames())->type($out, $this->type, $this->collation);
        if ($this->default !== null) {
            $out->keyword('DEFAULT')->node($this->default);
        }
    }
}
