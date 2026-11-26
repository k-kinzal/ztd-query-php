<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainInput;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to add a constraint to a domain: `ALTER DOMAIN name ADD constraint [ NOT VALID ]`.
 *
 * Rule: PG-DOMAIN-002. Mirrors `AlterDomainStmt` subtype 'C'. Release 16
 * writes a table constraint, release 17 a domain constraint (`DomainCheck`,
 * `DomainNotNull`). The constraint sees VALUE, whose type depends on the
 * undeclared domain (PG-DOMAIN-VALUE-001); kinds a domain cannot have are
 * diagnostics (PG-DOMAIN-CHECK-001).
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterDomainConstraint implements Statement, Relation
{
    use Snapshot;

    /**
     * @param DottedName $name The domain name
     * @param Clause $constraint The constraint
     */
    public function __construct(public readonly DottedName $name, public readonly Clause $constraint)
    {
    }

    /**
     * Derives the constraint, which sees the value as VALUE.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new DomainChecks())->constraints($derivation, [$this->constraint]);
        $values = new DomainInput();
        $this->constraint->deriveClause($derivation, $values->environment($derivation, $this, $values->undeclared($derivation, $this->name)));
    }

    /**
     * Answers the row the constraint sees besides VALUE: none.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact(new RowShape([]));
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DOMAIN')->node($this->name)->keyword('ADD')->node($this->constraint);
    }
}
