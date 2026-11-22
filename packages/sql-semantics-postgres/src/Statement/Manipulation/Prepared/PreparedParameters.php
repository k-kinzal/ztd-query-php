<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\ParameterDeclarations;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * The parenthesized parameter types of PREPARE.
 *
 * Mirrors the `argtypes` of PostgreSQL's `PrepareStmt`. Rule:
 * PG-PREPARE-PARAMETERS-001. The list is a relation of one unnamed slot per
 * declared type, in order: `$n` of the prepared statement has the type of
 * slot n and can be NULL. "If a parameter data type is not specified or is
 * declared as unknown, the type is inferred from the context in which the
 * parameter is first referenced": a parameter beyond the list stays
 * dependent on its use.
 * Source: https://www.postgresql.org/docs/17/sql-prepare.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PreparedParameters implements ParameterDeclarations
{
    use Snapshot;

    /**
     * @var non-empty-list<TypeName> The declared parameter types in order
     */
    public readonly array $types;

    /**
     * @param list<TypeName> $types The declared parameter types in order; at least one
     */
    public function __construct(array $types)
    {
        $this->types = Check::listOf($types, TypeName::class, 'The parameter types of PREPARE are type names, at least one.', 1);
    }

    /**
     * Answers that a parameter beyond the declared types is inferred from its use.
     */
    public function infersUndeclared(): bool
    {
        return true;
    }

    /**
     * Derives the types and answers the row of the declared parameters.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $slots = [];
        foreach ($this->types as $type) {
            $type->deriveClause($derivation, $environment);
            $slots[] = new OutputSlot(null, $type->typeFact($derivation->context), Nullability::Nullable);
        }

        return new RelationFact(new RowShape($slots));
    }

    /**
     * Writes the types in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->types)->symbol(')');
    }
}
