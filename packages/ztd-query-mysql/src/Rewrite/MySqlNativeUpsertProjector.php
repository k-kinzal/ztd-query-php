<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The my sql native upsert projector.
 */
final class MySqlNativeUpsertProjector
{
    private const INCOMING_ALIAS = '__ztd_incoming';

    private const EXISTING_ALIAS = '__ztd_existing';

    private readonly IdentifierQuoter $quoter;

    private readonly SqlLexerProfile $lexerProfile;

    /** @var non-empty-list<string> */
    private readonly array $incomingNamespaces;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->quoter = new MySqlIdentifierQuoter();
        $this->lexerProfile = MySqlLexerProfile::create();
        $this->incomingNamespaces = ['VALUES'];
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
        ?string $incomingNamespace = null,
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
                $incomingNamespace,
            );
            $incomingPredicate = $this->bindExpression(
                $conflictPredicate,
                $tableName,
                $tableColumns,
                self::INCOMING_ALIAS,
                $incomingNamespace,
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
            $evaluated = $this->bindExpression(
                $expression,
                $tableName,
                $tableColumns,
                incomingNamespace: $incomingNamespace,
            );
            $metadata = $this->quoter->quote($codec->valueColumn($index));
            $selects[] = "(SELECT $evaluated FROM $table AS $existingAlias WHERE $conflict LIMIT 1) AS $metadata";
        }
        if ($predicate !== null) {
            $evaluated = $this->bindExpression(
                $predicate,
                $tableName,
                $tableColumns,
                incomingNamespace: $incomingNamespace,
            );
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
     * @param array<string, array<int, string>> $candidateKeys Columns of each key, under the key's name
     * @param string $existingAlias Name the rows already there are selected under
     * @param string $incomingAlias Name the incoming rows are selected under
     *
     * @return string The test, and FALSE where there is no key to collide on
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
     * and VALUES(x) or the alias the statement gave means the incoming one.
     * A name inside a subquery belongs to that subquery and is left alone.
     *
     * @param string $expression Expression to rewrite, as written
     * @param string $tableName Table the statement writes to
     * @param list<string> $tableColumns Columns that table has
     * @param string $unqualifiedAlias Which row a bare name means
     * @param string|null $incomingNamespace Name the statement gave the incoming row, or null where it gave none
     *
     * @return string The expression, with every name saying which row it is from
     */
    public function bindExpression(
        string $expression,
        string $tableName,
        array $tableColumns,
        string $unqualifiedAlias = self::EXISTING_ALIAS,
        ?string $incomingNamespace = null,
    ): string {
        $tokens = SqlTokenStream::tokenize($expression, $this->lexerProfile)->significantTokens();
        $subqueryTokens = $this->subqueryTokenIndexes($tokens);
        $replacements = [];
        $incomingArgumentOffset = null;
        $columnNames = array_fill_keys(array_map('strtolower', $tableColumns), true);
        $namespaces = $this->incomingNamespaces;
        if ($incomingNamespace !== null) {
            $namespaces[] = $incomingNamespace;
        }
        $incomingNamespaces = array_fill_keys(array_map('strtolower', $namespaces), true);

        foreach ($tokens as $index => $token) {
            if (!$this->isIdentifier($token)) {
                continue;
            }
            if ($subqueryTokens[$index] ?? false) {
                continue;
            }
            if ($token->offset === $incomingArgumentOffset) {
                continue;
            }
            $name = $this->identifier($token);
            $next = $tokens[$index + 1] ?? null;
            $afterNext = $tokens[$index + 2] ?? null;
            if ($next?->text === '(' && isset($incomingNamespaces[strtolower($name)])) {
                $column = $afterNext;
                $close = $tokens[$index + 3] ?? null;
                if ($column !== null && $this->isIdentifier($column) && $close?->text === ')') {
                    $replacements[] = [
                        'offset' => $token->offset,
                        'length' => $close->endOffset() - $token->offset,
                        'value' => $this->qualified(self::INCOMING_ALIAS, $this->identifier($column)),
                    ];
                    $incomingArgumentOffset = $column->offset;
                }
                continue;
            }
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
     * @param list<SqlToken> $tokens The expression, as tokens
     *
     * @return array<int, true> Position => true, for every token inside a subquery
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
     * @param SqlToken $token Token to test
     *
     * @return bool True for a bare word or a quoted name
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
     * @return string The name, with the quoting taken off, or the token's own text where it is not a name
     */
    public function identifier(SqlToken $token): string
    {
        $identifier = SqlTokenStream::tokenize($token->text, $this->lexerProfile)->identifierAt();

        return $identifier === null ? $token->text : $identifier['name'];
    }

    /**
     * Writes a column as belonging to one of the rows being compared.
     *
     * @param string $alias Name that row is selected under
     * @param string $column Column of it
     *
     * @return string The column, written as MySQL would name it
     */
    public function qualified(string $alias, string $column): string
    {
        return $this->quoter->quote($alias) . '.' . $this->quoter->quote($column);
    }
}
