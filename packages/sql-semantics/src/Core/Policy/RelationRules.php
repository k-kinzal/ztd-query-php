<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

/**
 * Where a grammar writes table names, and which of those positions declare, drop, or merely name a table.
 *
 * A database package describes its grammar with symbols rather than with
 * generated classes: the symbols whose values are table names, and the forms
 * whose names are declarations, drops, common table expressions, or names of
 * other objects that are not tables. Every other table name is a reference.
 *
 * It also describes where common table expressions are visible: the
 * symbols at which a form writes its WITH clause, the fixed word that makes
 * the clause recursive, which expressions of its own clause the body of an
 * expression can name, and the positions, such as the target of a DML
 * statement, whose names never refer to a common table expression.
 *
 * @phpstan-type Site array{rule: string, requires?: list<string>, name?: string, pair?: list<string>, names?: string, list?: array{string, list<string>}, conditional?: string, type?: array{string, list<string>}}
 * @visibility SqlSemantics
 */
final class RelationRules
{
    /**
     * @param list<string> $nameSymbols Grammar symbols whose values are complete table names
     * @param list<Site> $declarations Forms that declare the table they name
     * @param list<Site> $drops Forms that drop the tables they name
     * @param list<Site> $commonTableExpressions Forms that define a common table expression by name
     * @param list<Site> $ignored Forms whose names are not tables, such as views, or are new names
     * @param list<Site> $pairs Forms that write a table name as separate values, such as a name and a schema
     * @param array<string, array<string, list<int>>> $parts The child positions that spell the name, by rule and joined symbols, where not every child does
     * @param list<string> $withClauses Grammar symbols at which a form writes the WITH clause whose expressions its other values can name
     * @param string $recursive The fixed word, by its terminal name, that makes a WITH clause recursive
     * @param WithVisibility $visibility The expressions of a plain WITH clause the body of each of them can name
     * @param WithVisibility $recursiveVisibility The expressions of a recursive WITH clause the body of each of them can name
     * @param list<string> $targets Grammar symbols whose names are tables even where a common table expression of that name is visible
     */
    public function __construct(
        public readonly array $nameSymbols,
        public readonly array $declarations = [],
        public readonly array $drops = [],
        public readonly array $commonTableExpressions = [],
        public readonly array $ignored = [],
        public readonly array $pairs = [],
        public readonly array $parts = [],
        public readonly array $withClauses = [],
        public readonly string $recursive = 'RECURSIVE',
        public readonly WithVisibility $visibility = WithVisibility::Preceding,
        public readonly WithVisibility $recursiveVisibility = WithVisibility::All,
        public readonly array $targets = [],
    ) {
    }

    /**
     * Answers the sites of a kind that match a form, by its rule and symbols.
     *
     * @param list<Site> $sites
     * @param list<string> $symbols
     * @return list<Site>
     */
    public static function matching(array $sites, string $rule, array $symbols): array
    {
        return array_values(array_filter($sites, static fn (array $site): bool => $site['rule'] === $rule && array_diff($site['requires'] ?? [], $symbols) === []));
    }
}
