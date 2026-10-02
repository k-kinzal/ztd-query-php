<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\BareLabelKeywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\ColumnNameKeywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\ReservedKeywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\TypeFunctionNameKeywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\UnreservedKeywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;

/**
 * Reads a keyword used as a name.
 *
 * Rule: PG-KEYWORD-NAME-001. Scope: `unreserved_keyword`,
 * `col_name_keyword`, `type_func_name_keyword`, `reserved_keyword` and
 * `bare_label_keyword`. The name is the keyword folded to lower case, exactly
 * as an unquoted identifier is folded. Source:
 * https://www.postgresql.org/docs/17/sql-keywords-appendix.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Keywords
{
    /**
     * @var array<string, array<string, int>>
     */
    private static array $claimed = [];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Answers the lower-case word of a keyword category node.
     *
     * @throws ImplementationGap When the production is not a listed keyword production
     */
    public function word(Node $keyword): string
    {
        $form = $this->lowering->productions->form($keyword);
        self::$claimed[$keyword->name] ??= array_flip(match ($keyword->name) {
            'unreserved_keyword' => UnreservedKeywords::SIGNATURES,
            'col_name_keyword' => ColumnNameKeywords::SIGNATURES,
            'type_func_name_keyword' => TypeFunctionNameKeywords::SIGNATURES,
            'reserved_keyword' => ReservedKeywords::SIGNATURES,
            'bare_label_keyword' => BareLabelKeywords::SIGNATURES,
            default => throw ImplementationGap::production($form),
        });
        if (!isset(self::$claimed[$keyword->name][$form->signature])) {
            throw ImplementationGap::production($form);
        }

        return (new Identifiers())->fold($form->token(0)->text);
    }
}
