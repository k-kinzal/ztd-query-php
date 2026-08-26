<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The pg sql native upsert projector.
 */
final class PgSqlNativeUpsertProjector
{
    private const INCOMING_ALIAS = '__ztd_incoming';

    private const EXISTING_ALIAS = '__ztd_existing';

    private readonly IdentifierQuoter $quoter;

    /** @var non-empty-list<string> */
    private readonly array $incomingNamespaces;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->quoter = new PgSqlIdentifierQuoter();
        $this->incomingNamespaces = ['EXCLUDED'];
    }

    /**
     * @param list<string> $tableColumns
     * @param array<string, array<int, string>> $candidateKeys
     * @param array<string, string> $assignments
     */
    public function project(
        string $incomingSql,
        string $tableName,
        array $tableColumns,
        array $candidateKeys,
        array $assignments,
        ?string $predicate = null,
        ?string $conflictPredicate = null,
    ): string {
        if ($assignments === [] || $candidateKeys === []) {
            return $incomingSql;
        }

        $incomingAlias = $this->quoter->quote(self::INCOMING_ALIAS);
        $existingAlias = $this->quoter->quote(self::EXISTING_ALIAS);
        $table = $this->quoter->quote($tableName);
        $conflict = $this->conflictPredicate($candidateKeys, $existingAlias, $incomingAlias);
        if ($conflictPredicate !== null) {
            $existingPredicate = $this->bindExpression(
                $conflictPredicate,
                $tableName,
                $tableColumns,
                self::EXISTING_ALIAS,
            );
            $incomingPredicate = $this->bindExpression(
                $conflictPredicate,
                $tableName,
                $tableColumns,
                self::INCOMING_ALIAS,
            );
            $conflict = "($conflict AND ($existingPredicate) AND ($incomingPredicate))";
        }
        $selects = [];
        foreach ($tableColumns as $column) {
            $quoted = $this->quoter->quote($column);
            $selects[] = "$incomingAlias.$quoted AS $quoted";
        }

        $codec = new UpsertMutationRow();
        foreach (array_values($assignments) as $index => $expression) {
            $evaluated = $this->bindExpression($expression, $tableName, $tableColumns);
            $metadata = $this->quoter->quote($codec->valueColumn($index));
            $selects[] = "(SELECT $evaluated FROM $table AS $existingAlias WHERE $conflict LIMIT 1) AS $metadata";
        }
        if ($predicate !== null) {
            $evaluated = $this->bindExpression($predicate, $tableName, $tableColumns);
            $metadata = $this->quoter->quote($codec->predicateColumn());
            $selects[] = "(SELECT $evaluated FROM $table AS $existingAlias WHERE $conflict LIMIT 1) AS $metadata";
        }

        return 'SELECT ' . implode(', ', $selects) . " FROM ($incomingSql) AS $incomingAlias";
    }

    /**
     * Answers the test that says an incoming row collides with one already there.
     *
     * A row collides when it agrees with an existing one on any whole
     * candidate key, so the test is written as one alternative per key.
     *
     * @param array<string, array<int, string>> $candidateKeys The candidate keys
     * @param string $existingAlias The existing alias
     * @param string $incomingAlias The incoming alias
     *
     * @return string What it answers
     */
    public function conflictPredicate(array $candidateKeys, string $existingAlias, string $incomingAlias): string
    {
        $keys = [];
        foreach ($candidateKeys as $columns) {
            if ($columns === []) {
                continue;
            }
            $comparisons = [];
            foreach ($columns as $column) {
                $quoted = $this->quoter->quote($column);
                $comparisons[] = "$existingAlias.$quoted = $incomingAlias.$quoted";
            }
            $keys[] = '(' . implode(' AND ', $comparisons) . ')';
        }

        return $keys === [] ? 'FALSE' : '(' . implode(' OR ', $keys) . ')';
    }

    /**
     * Writes an expression so that each name in it says which row it means.
     *
     * A bare column name in a conflict clause means the row already there,
     * and the name the statement gave the incoming row means that one. A name
     * inside a subquery belongs to that subquery and is left alone.
     *
     * @param string $expression Expression to read, as written
     * @param string $tableName Table it belongs to
     * @param list<string> $tableColumns The table columns
     * @param string $unqualifiedAlias The unqualified alias
     *
     * @return string What it answers
     */
    public function bindExpression(
        string $expression,
        string $tableName,
        array $tableColumns,
        string $unqualifiedAlias = self::EXISTING_ALIAS,
    ): string {
        $tokens = SqlTokenStream::tokenize($expression, PgSqlLexerProfile::create())->significantTokens();
        $subqueryTokens = $this->subqueryTokenIndexes($tokens);
        $replacements = [];
        $columnNames = array_fill_keys(array_map('strtolower', $tableColumns), true);
        $incomingNamespaces = array_fill_keys(array_map('strtolower', $this->incomingNamespaces), true);

        foreach ($tokens as $index => $token) {
            if (!$this->isIdentifier($token)) {
                continue;
            }
            if ($subqueryTokens[$index] ?? false) {
                continue;
            }
            $name = $this->identifier($token);
            $next = $tokens[$index + 1] ?? null;
            $afterNext = $tokens[$index + 2] ?? null;
            if ($next?->text === '.' && $afterNext !== null && $this->isIdentifier($afterNext)) {
                $namespace = strtolower($name);
                $alias = isset($incomingNamespaces[$namespace])
                    ? self::INCOMING_ALIAS
                    : (strcasecmp($name, $tableName) === 0 ? self::EXISTING_ALIAS : null);
                if ($alias !== null) {
                    $replacements[] = [
                        'offset' => $token->offset,
                        'length' => $afterNext->endOffset() - $token->offset,
                        'value' => $this->qualified($alias, $this->identifier($afterNext)),
                    ];
                }
                continue;
            }
            $previous = $tokens[$index - 1] ?? null;
            if ($previous?->text === '.' || $next?->text === '(' || !isset($columnNames[strtolower($name)])) {
                continue;
            }
            $replacements[] = [
                'offset' => $token->offset,
                'length' => strlen($token->text),
                'value' => $this->qualified($unqualifiedAlias, $name),
            ];
        }

        foreach (array_reverse($replacements) as $replacement) {
            $expression = substr_replace(
                $expression,
                $replacement['value'],
                $replacement['offset'],
                $replacement['length'],
            );
        }

        return $expression;
    }

    /**
     * Answers which tokens belong to a subquery rather than to the expression itself.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     *
     * @return array<int, true> What it answers
     */
    public function subqueryTokenIndexes(array $tokens): array
    {
        $indexes = [];
        foreach ($tokens as $start => $token) {
            if (!$token->isKeyword('SELECT') || $token->isTopLevel()) {
                continue;
            }
            for ($index = $start; isset($tokens[$index]); ++$index) {
                $candidate = $tokens[$index];
                if ($candidate->depth < $token->depth) {
                    break;
                }
                $indexes[$index] = true;
            }
        }

        return $indexes;
    }

    /**
     * Reports whether a token is a name at all.
     *
     * @param SqlToken $token Token to read
     *
     * @return bool What it answers
     */
    public function isIdentifier(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true);
    }

    /**
     * Answers the name a token stands for.
     *
     * @param SqlToken $token Token to read
     *
     * @return string What it answers
     */
    public function identifier(SqlToken $token): string
    {
        $identifier = SqlTokenStream::tokenize($token->text, PgSqlLexerProfile::create())->identifierAt();

        return $identifier === null ? $token->text : $identifier['name'];
    }

    /**
     * Writes a column as belonging to one of the rows being compared.
     *
     * @param string $alias Name the statement gave it
     * @param string $column Column to read
     *
     * @return string What it answers
     */
    public function qualified(string $alias, string $column): string
    {
        return $this->quoter->quote($alias) . '.' . $this->quoter->quote($column);
    }
}
