<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the catalog family: schemas, databases, tablespaces, extensions, foreign data, languages, access methods, publications, subscriptions, text search, collations, conversions, domains, types, casts and transforms.
 *
 * Rule: PG-CATALOG-001 (stub — the family implements the bodies; the method
 * signatures are the stable contract and a family may narrow a return type).
 * Scope: see `.agent/plan-pg.md`, family Catalog. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class Catalogs
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `CreateSchemaStmt`, `CreatedbStmt`, `CreateExtensionStmt` or `AlterDomainStmt`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }
}
