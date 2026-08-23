<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class MySqlNativeUpsertProjector
{
    private const INCOMING_ALIAS = '__ztd_incoming';

    private const EXISTING_ALIAS = '__ztd_existing';

    private readonly IdentifierQuoter $quoter;

    private readonly SqlLexerProfile $lexerProfile;

    /** @var non-empty-list<string> */
    private readonly array $incomingNamespaces;

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
     * @param array<string, array<int, string>> $candidateKeys
     */
    private function conflictPredicate(array $candidateKeys, string $existingAlias, string $incomingAlias): string
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

    /** @param list<string> $tableColumns */
    private function bindExpression(
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
     * @param list<SqlToken> $tokens
     * @return array<int, true>
     */
    private function subqueryTokenIndexes(array $tokens): array
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

    private function isIdentifier(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true);
    }

    private function identifier(SqlToken $token): string
    {
        $identifier = SqlTokenStream::tokenize($token->text, $this->lexerProfile)->identifierAt();

        return $identifier === null ? $token->text : $identifier['name'];
    }

    private function qualified(string $alias, string $column): string
    {
        return $this->quoter->quote($alias) . '.' . $this->quoter->quote($column);
    }
}
