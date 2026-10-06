<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainInput;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a domain: a base type with a default, a collation and constraints.
 *
 * Rule: PG-DOMAIN-001. Mirrors `CreateDomainStmt`: name, base type and the
 * constraints and collation in the order written; the optional AS is not
 * kept. The constraint expressions see the value being checked as VALUE, of
 * the base type (PG-DOMAIN-VALUE-001); the statement is the relation
 * occurrence that offers it, with an empty row. Constraints a domain cannot
 * have are diagnostics (PG-DOMAIN-CHECK-001). A domain is a type, not a
 * relation, so the statement provides no declaration.
 * Source: https://www.postgresql.org/docs/17/sql-createdomain.html. Status: Implemented.
 *
 * @visibility public
 * @example Resolving VALUE in a domain constraint to the base type
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN posint AS int4 CHECK (VALUE > 0)');
 *     [$operation->facts->diagnostics, $operation->toString()] // => [[], 'CREATE DOMAIN posint int4 CHECK (value > 0)']
 */
final class CreateDomain implements Statement, Relation
{
    use Snapshot;

    /**
     * @var list<Clause> The constraints and the collation in the order written
     */
    public readonly array $constraints;

    /**
     * @param DottedName $name The domain name
     * @param TypeName $type The base type
     * @param list<Clause> $constraints The constraints and the collation in the order written
     */
    public function __construct(public readonly DottedName $name, public readonly TypeName $type, array $constraints = [])
    {
        $this->constraints = Check::listOf($constraints, Clause::class, 'Domain constraints are clauses.');
    }

    /**
     * Derives the base type and the constraints, which see the value as VALUE.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new DomainChecks())->constraints($derivation, $this->constraints);
        $this->type->deriveClause($derivation, new Environment($derivation->context));
        $environment = (new DomainInput())->environment($derivation, $this, $this->type->typeFact($derivation->context));
        foreach ($this->constraints as $constraint) {
            $constraint->deriveClause($derivation, $environment);
        }
    }

    /**
     * Answers the row the constraints see besides VALUE: none.
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
        $out->keyword('CREATE', 'DOMAIN')->node($this->name)->node($this->type);
        foreach ($this->constraints as $constraint) {
            $out->node($constraint);
        }
    }
}
