<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Dialect;

use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Restores native operators that cannot be represented by a CTE column cast.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class MySqlTypeSemantics
{
    /**
     * @param ShadowTables $tables Table name => what the shadow holds for it
     */
    public function rewrite(string $sql, array $tables): string
    {
        [$qualified, $unqualified] = $this->enumColumns($tables);
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        $edits = $this->comparisonEdits($sql, $tokens, $qualified, $unqualified);
        foreach ($this->orderByEdits($sql, $tokens, $qualified, $unqualified) as $key => $edit) {
            $edits[$key] = $edit;
        }

        uasort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr($sql, 0, $edit['start']) . $edit['replacement'] . substr($sql, $edit['end']);
        }

        return $sql;
    }

    /**
     * Answers which columns are enumerations, and what each may hold.
     *
     * A column named without its table is only unambiguous while every table
     * that has one declares it the same way, so a name two tables disagree
     * about answers nothing rather than answering one of them.
     *
     * @param ShadowTables $tables Table name => what the shadow holds for it
     *
     * @return array{array<string, list<string>>, array<string, list<string>|null>} Members under the qualified name, and under the bare one where it is unambiguous
     */
    public function enumColumns(array $tables): array
    {
        $qualified = [];
        $unqualified = [];
        foreach ($tables as $table => $context) {
            if (isset($context['viewSql'])) {
                continue;
            }
            foreach ($context['columnTypes'] as $column => $type) {
                $members = $this->enumMembers($type->nativeType);
                if ($members === []) {
                    continue;
                }
                $qualified[strtolower("$table.$column")] = $members;
                $key = strtolower($column);
                if (!array_key_exists($key, $unqualified)) {
                    $unqualified[$key] = $members;
                } elseif ($unqualified[$key] !== $members) {
                    $unqualified[$key] = null;
                }
            }
        }

        return [$qualified, $unqualified];
    }

    /**
     * Answers the edits that make a comparison against an enumeration order by its members.
     *
     * MySQL orders an enumeration by the order its members were declared in,
     * not alphabetically, and the shadow holds the member's text. Both sides
     * of the comparison are rewritten to that position, so the comparison
     * means what it meant against the real column.
     *
     * @param string $sql The statement, as written
     * @param list<SqlToken> $tokens The same statement, as tokens
     * @param array<string, list<string>> $qualified Members under each table-qualified column name
     * @param array<string, list<string>|null> $unqualified Members under each bare column name
     *
     * @return array<int, array{start: int, end: int, replacement: string}> The edits, under where each starts
     */
    public function comparisonEdits(string $sql, array $tokens, array $qualified, array $unqualified): array
    {
        $edits = [];
        foreach ($tokens as $index => $token) {
            $column = $this->columnAt($sql, $tokens, $index, $qualified, $unqualified);
            if ($column === null) {
                continue;
            }
            $operatorIndex = $index + $column['length'];
            $operator = $this->orderedOperatorAt($tokens, $operatorIndex);
            if ($operator === null) {
                continue;
            }
            $value = $tokens[$operatorIndex + $operator['length']] ?? null;
            if ($value === null || $value->kind !== SqlTokenKind::String) {
                continue;
            }

            $this->addRankEdit($edits, $column['token'], $column['members']);
            $this->addRankEdit($edits, $value, $column['members']);
        }

        return $edits;
    }

    /**
     * Answers the edits that make an ORDER BY an enumeration order by its members.
     *
     * Only a column ordered by on its own is rewritten: one that is part of a
     * larger expression is that expression's business, and the expression
     * already has whatever meaning it has.
     *
     * @param string $sql The statement, as written
     * @param list<SqlToken> $tokens The same statement, as tokens
     * @param array<string, list<string>> $qualified Members under each table-qualified column name
     * @param array<string, list<string>|null> $unqualified Members under each bare column name
     *
     * @return array<int, array{start: int, end: int, replacement: string}> The edits, under where each starts
     */
    public function orderByEdits(string $sql, array $tokens, array $qualified, array $unqualified): array
    {
        $edits = [];
        foreach ($tokens as $index => $order) {
            if (!$order->isKeyword('ORDER')) {
                continue;
            }
            $by = $tokens[$index + 1] ?? null;
            if ($by === null || !$by->isKeyword('BY')) {
                continue;
            }
            $depth = $order->depth;
            $bracketDepth = $order->bracketDepth;
            $afterBy = false;
            foreach ($tokens as $item => $token) {
                if (!$afterBy) {
                    $afterBy = $token === $by;
                    continue;
                }
                if ($token->depth !== $depth || $token->bracketDepth !== $bracketDepth) {
                    continue;
                }
                if ($token->isKeyword('LIMIT') || $token->isKeyword('FOR') || $token->isKeyword('LOCK')) {
                    break;
                }
                $column = $this->columnAt($sql, $tokens, $item, $qualified, $unqualified);
                if ($column === null) {
                    continue;
                }
                $after = $tokens[$item + $column['length']] ?? null;
                if ($after !== null
                    && $after->depth === $depth
                    && $after->bracketDepth === $bracketDepth
                    && !$after->isKeyword('ASC')
                    && !$after->isKeyword('DESC')
                    && !($after->kind === SqlTokenKind::Symbol && $after->text === ',')
                    && !$after->isKeyword('LIMIT')
                    && !$after->isKeyword('FOR')
                    && !$after->isKeyword('LOCK')
                ) {
                    continue;
                }
                $this->addRankEdit($edits, $column['token'], $column['members']);
            }
        }

        return $edits;
    }

    /**
     * Answers the enumeration column named here, if one is.
     *
     * @param string $sql The statement, as written
     * @param list<SqlToken> $tokens The same statement, as tokens
     * @param int $index Where to read
     * @param array<string, list<string>> $qualified Members under each table-qualified column name
     * @param array<string, list<string>|null> $unqualified Members under each bare column name
     *
     * @return array{token: SqlToken, length: int, members: list<string>}|null The name as written, how many tokens it took, and what it may hold
     */
    public function columnAt(string $sql, array $tokens, int $index, array $qualified, array $unqualified): ?array
    {
        $first = $tokens[$index];
        if (($tokens[$index - 1] ?? null)?->text === '.') {
            return null;
        }

        $length = 1;
        $column = $this->identifierName($first);
        $qualifiedKey = null;
        $dot = $tokens[$index + 1] ?? null;
        $qualifiedColumn = $tokens[$index + 2] ?? null;
        if ($dot?->text === '.' && $qualifiedColumn !== null && $this->isIdentifier($qualifiedColumn)) {
            $length = 3;
            $column = $this->identifierName($qualifiedColumn);
            $qualifiedKey = strtolower($this->identifierName($first) . '.' . $column);
        }

        $members = $qualifiedKey === null
            ? ($unqualified[strtolower($column)] ?? null)
            : ($qualified[$qualifiedKey] ?? null);
        if ($members === null) {
            return null;
        }

        $last = $tokens[$index + $length - 1];

        return [
            'token' => new SqlToken(
                SqlTokenKind::Word,
                substr($sql, $first->offset, $last->endOffset() - $first->offset),
                $first->offset,
                $first->depth,
                $first->bracketDepth,
            ),
            'length' => $length,
            'members' => $members,
        ];
    }

    /**
     * Answers whether an ordering comparison is written here, and how long it is.
     *
     * Only the comparisons that put values in an order matter: equality means
     * the same thing against text as against a position.
     *
     * @param list<SqlToken> $tokens The statement, as tokens
     * @param int $index Where to look
     *
     * @return array{length: int}|null How many tokens the operator is, or null where it is not an ordering one
     */
    public function orderedOperatorAt(array $tokens, int $index): ?array
    {
        $first = $tokens[$index] ?? null;
        if ($first === null || $first->kind !== SqlTokenKind::Symbol || ($first->text !== '<' && $first->text !== '>')) {
            return null;
        }
        $second = $tokens[$index + 1] ?? null;

        return ['length' => $second?->text === '=' ? 2 : 1];
    }

    /**
     * Records an edit rewriting something as its position among the members.
     *
     * @param array<int, array{start: int, end: int, replacement: string}> $edits Edits so far, added to in place
     * @param SqlToken $token What to rewrite
     * @param list<string> $members The members, in the order they were declared
     */
    public function addRankEdit(array &$edits, SqlToken $token, array $members): void
    {
        $memberSql = array_map(
            static fn (string $member): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $member) . "'",
            $members,
        );
        $edits[$token->offset] = [
            'start' => $token->offset,
            'end' => $token->endOffset(),
            'replacement' => 'FIELD(' . $token->text . ', ' . implode(', ', $memberSql) . ')',
        ];
    }

    /**
     * Answers what an ENUM declaration says a column may hold.
     *
     * @param string $nativeType The declaration, as the platform wrote it
     *
     * @return list<string> The members, in the order declared, or none where this declares no enumeration
     */
    public function enumMembers(string $nativeType): array
    {
        $open = strpos($nativeType, '(');
        if ($open === false) {
            return [];
        }
        if (strtoupper(trim(substr($nativeType, 0, $open))) !== 'ENUM') {
            return [];
        }
        $inner = substr(trim($nativeType), $open + 1, -1);
        $members = [];
        foreach (SqlTokenStream::tokenize($inner, MySqlLexerProfile::create())->splitTopLevel() as $literal) {
            if (strlen($literal) < 2) {
                return [];
            }
            $quote = $literal[0];
            if ($quote !== "'" && $quote !== '"') {
                return [];
            }
            if ($literal[strlen($literal) - 1] !== $quote) {
                return [];
            }
            $value = substr($literal, 1, -1);
            $members[] = str_replace([$quote . $quote, '\\' . $quote, '\\\\'], [$quote, $quote, '\\'], $value);
        }

        return $members;
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
     * @return string The name, with the quoting taken off
     */
    public function identifierName(SqlToken $token): string
    {
        return $token->kind === SqlTokenKind::QuotedIdentifier
            ? substr($token->text, 1, -1)
            : $token->text;
    }
}
