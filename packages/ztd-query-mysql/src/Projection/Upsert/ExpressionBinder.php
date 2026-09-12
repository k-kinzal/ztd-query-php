<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\Upsert;

use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Expression Binder.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ExpressionBinder
{
    /**
     * Incoming alias.
     */
    public const INCOMING_ALIAS = '__ztd_incoming';
    /**
     * Existing alias.
     */
    public const EXISTING_ALIAS = '__ztd_existing';
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly MySqlIdentifierQuoter $quoter, private readonly SqlLexerProfile $lexerProfile)
    {
    }
    /**
     * @param list<string> $tableColumns
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
        $namespaces = ['VALUES'];
        if ($incomingNamespace !== null) {
            $namespaces[] = $incomingNamespace;
        }
        $incomingNamespaces = array_fill_keys(array_map('strtolower', $namespaces), true);

        foreach ($tokens as $index => $token) {
            if (!(in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true))) {
                continue;
            }
            if ($subqueryTokens[$index] ?? false) {
                continue;
            }
            if ($token->offset === $incomingArgumentOffset) {
                continue;
            }
            $replacement = $this->bindingAt($tokens, $index, $columnNames, $incomingNamespaces, $tableName, $unqualifiedAlias);
            if ($replacement !== null) {
                $replacements[] = $replacement;
                $incomingArgumentOffset = $replacement['skip'] ?? $incomingArgumentOffset;
            }
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
     * Identifier for the supplied MySQL input.
     */
    public function identifier(SqlToken $token): string
    {
        $identifier = SqlTokenStream::tokenize($token->text, $this->lexerProfile)->identifierAt();

        return $identifier === null ? $token->text : $identifier['name'];
    }
    /**
     * @param list<SqlToken> $tokens
     * @param array<string, true> $columnNames
     * @param array<string, true> $incomingNamespaces
     * @return array{offset: int, length: int, value: string, skip?: int}|null
     */
    public function bindingAt(array $tokens, int $index, array $columnNames, array $incomingNamespaces, string $tableName, string $unqualifiedAlias): ?array
    {
        $token = $tokens[$index];
        $name = $this->identifier($token);
        $next = $tokens[$index + 1] ?? null;
        $afterNext = $tokens[$index + 2] ?? null;
        if ($next?->text === '(' && isset($incomingNamespaces[strtolower($name)])) {
            return $this->incomingBinding($tokens, $index);
        }
        if ($next?->text === '.' && $afterNext !== null && in_array($afterNext->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true)) {
            $alias = isset($incomingNamespaces[strtolower($name)])
                ? self::INCOMING_ALIAS
                : (strcasecmp($name, $tableName) === 0 ? self::EXISTING_ALIAS : null);
            return $alias === null ? null : $this->qualifiedBinding($token, $afterNext, $alias);
        }
        $previous = $tokens[$index - 1] ?? null;
        if ($previous?->text === '.' || $next?->text === '(' || !isset($columnNames[strtolower($name)])) {
            return null;
        }
        return [
            'offset' => $token->offset,
            'length' => strlen($token->text),
            'value' => (new QualifiedColumn($this->quoter))->qualified($unqualifiedAlias, $name),
        ];
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{offset: int, length: int, value: string, skip: int}|null
     */
    public function incomingBinding(array $tokens, int $index): ?array
    {
        $token = $tokens[$index];
        $column = $tokens[$index + 2] ?? null;
        $close = $tokens[$index + 3] ?? null;
        if ($column === null || !in_array($column->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true) || $close?->text !== ')') {
            return null;
        }
        return [
            'offset' => $token->offset,
            'length' => $close->endOffset() - $token->offset,
            'value' => (new QualifiedColumn($this->quoter))->qualified(self::INCOMING_ALIAS, $this->identifier($column)),
            'skip' => $column->offset,
        ];
    }

    /**
     * @return array{offset: int, length: int, value: string}
     */
    public function qualifiedBinding(SqlToken $qualifier, SqlToken $column, string $alias): array
    {
        return [
            'offset' => $qualifier->offset,
            'length' => $column->endOffset() - $qualifier->offset,
            'value' => (new QualifiedColumn($this->quoter))->qualified($alias, $this->identifier($column)),
        ];
    }

}
