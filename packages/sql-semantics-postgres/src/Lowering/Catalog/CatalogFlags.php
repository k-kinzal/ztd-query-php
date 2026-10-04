<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;

/**
 * Lowers the optional keywords of the catalog family.
 *
 * Rule: PG-CATALOG-FLAG-001. Scope: `opt_if_exists`, `opt_if_not_exists`,
 * `opt_trusted`. A flag is whether its keywords are written; each is
 * significant. Source: https://www.postgresql.org/docs/17/sql-dropcast.html,
 * https://www.postgresql.org/docs/17/sql-altertype.html, https://www.postgresql.org/docs/17/sql-createlanguage.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class CatalogFlags
{
    /**
     * Whether each flag production writes its keywords.
     */
    private const PRESENT = [
        'opt_if_exists: IF_P EXISTS' => true, 'opt_if_exists:' => false,
        'opt_if_not_exists: IF_P NOT EXISTS' => true, 'opt_if_not_exists:' => false,
        'opt_trusted: TRUSTED' => true, 'opt_trusted:' => false,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether `opt_if_exists`, `opt_if_not_exists` or `opt_trusted` writes its keywords.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function present(Node $flag): bool
    {
        $form = $this->lowering->productions->form($flag);

        return self::PRESENT[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
