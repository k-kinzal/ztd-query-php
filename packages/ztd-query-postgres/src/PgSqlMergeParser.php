<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\CteShadowComposer;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class PgSqlMergeParser
{
    private CteShadowComposer $cteComposer;

    public function __construct()
    {
        $this->cteComposer = new CteShadowComposer();
    }

    public function parse(string $sql): PgSqlMergeStatement
    {
        $statementSql = trim($this->cteComposer->statementSql($sql));
        $tokens = SqlTokenStream::tokenize($statementSql)->significantTokens();
        $index = 0;
        if (($tokens[$index] ?? null)?->isKeyword('MERGE') !== true) {
            throw new UnsupportedSqlException($sql, 'Malformed MERGE statement');
        }
        $index++;
        if (($tokens[$index] ?? null)?->isKeyword('INTO') === true) {
            $index++;
        }
        if (($tokens[$index] ?? null)?->isKeyword('ONLY') === true) {
            $index++;
        }

        $target = $this->relationAt($statementSql, $tokens, $index);
        if ($target === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve MERGE target');
        }
        $index = $target['next'];
        if ($this->isSymbol($tokens[$index] ?? null, '*')) {
            $index++;
        }

        $usingIndex = $this->keywordIndex($tokens, 'USING', $index);
        if ($usingIndex === null) {
            throw new UnsupportedSqlException($sql, 'MERGE requires USING');
        }
        $targetAlias = $this->targetAlias($sql, $tokens, $index, $usingIndex, $target['name']);

        $whenIndices = $this->mergeWhenIndices($tokens, $usingIndex + 1);
        $firstWhen = $whenIndices[0] ?? null;
        if ($firstWhen === null) {
            throw new UnsupportedSqlException($sql, 'MERGE requires a WHEN clause');
        }
        $onIndex = $this->lastKeywordIndex($tokens, 'ON', $usingIndex + 1, $firstWhen);
        if ($onIndex === null) {
            throw new UnsupportedSqlException($sql, 'MERGE requires an ON condition');
        }

        $sourceSql = trim(substr(
            $statementSql,
            $tokens[$usingIndex]->endOffset(),
            $tokens[$onIndex]->offset - $tokens[$usingIndex]->endOffset(),
        ));
        $joinConditionSql = trim(substr(
            $statementSql,
            $tokens[$onIndex]->endOffset(),
            $tokens[$firstWhen]->offset - $tokens[$onIndex]->endOffset(),
        ));
        if ($sourceSql === '' || $joinConditionSql === '') {
            throw new UnsupportedSqlException($sql, 'MERGE requires a source and join condition');
        }

        $clauses = [];
        foreach ($whenIndices as $clauseIndex => $whenIndex) {
            $end = isset($whenIndices[$clauseIndex + 1])
                ? $tokens[$whenIndices[$clauseIndex + 1]]->offset
                : strlen($statementSql);
            $clauseSql = trim(substr($statementSql, $tokens[$whenIndex]->offset, $end - $tokens[$whenIndex]->offset));
            $clauses[] = $this->parseClause($sql, $clauseSql);
        }
        if ($clauses === []) {
            throw new UnsupportedSqlException($sql, 'MERGE requires a WHEN clause');
        }

        return new PgSqlMergeStatement(
            $target['name'],
            $target['sql'],
            $targetAlias,
            $sourceSql,
            $joinConditionSql,
            $clauses,
        );
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{name: string, sql: string, next: int}|null
     */
    private function relationAt(string $sql, array $tokens, int $index): ?array
    {
        $first = $tokens[$index] ?? null;
        $name = $first !== null ? $this->identifierName($first) : null;
        if ($first === null || $name === null) {
            return null;
        }

        $start = $first->offset;
        $end = $first->endOffset();
        $index++;
        while ($this->isSymbol($tokens[$index] ?? null, '.')) {
            $component = $tokens[$index + 1] ?? null;
            $componentName = $component !== null ? $this->identifierName($component) : null;
            if ($component === null || $componentName === null) {
                return null;
            }
            $name = $componentName;
            $end = $component->endOffset();
            $index += 2;
        }

        return [
            'name' => $name,
            'sql' => substr($sql, $start, $end - $start),
            'next' => $index,
        ];
    }

    /** @param list<SqlToken> $tokens */
    private function targetAlias(
        string $sql,
        array $tokens,
        int $start,
        int $end,
        string $default,
    ): string {
        if ($start === $end) {
            return $default;
        }
        if (($tokens[$start] ?? null)?->isKeyword('AS') === true) {
            $start++;
        }
        if ($start + 1 !== $end) {
            throw new UnsupportedSqlException($sql, 'Malformed MERGE target alias');
        }
        $alias = $this->identifierName($tokens[$start]);
        if ($alias === null) {
            throw new UnsupportedSqlException($sql, 'Malformed MERGE target alias');
        }

        return $alias;
    }

    private function parseClause(string $originalSql, string $clauseSql): PgSqlMergeClause
    {
        $clauseSql = rtrim($clauseSql, "; \t\n\r\0\x0B");
        $tokens = SqlTokenStream::tokenize($clauseSql)->significantTokens();
        $index = 1;
        $matchKind = PgSqlMergeMatchKind::Matched;
        if (($tokens[$index] ?? null)?->isKeyword('NOT') === true) {
            $matchKind = PgSqlMergeMatchKind::NotMatched;
            $index++;
        }
        if (($tokens[$index] ?? null)?->isKeyword('MATCHED') !== true) {
            throw new UnsupportedSqlException($originalSql, 'Malformed MERGE WHEN clause');
        }
        $index++;
        if (($tokens[$index] ?? null)?->isKeyword('BY') === true) {
            throw new UnsupportedSqlException($originalSql, 'MERGE BY SOURCE and BY TARGET are not supported');
        }

        $thenIndex = $this->keywordIndexOutsideCase($tokens, 'THEN', $index);
        if ($thenIndex === null) {
            throw new UnsupportedSqlException($originalSql, 'MERGE WHEN clause requires THEN');
        }
        $conditionSql = null;
        if ($index !== $thenIndex) {
            if (($tokens[$index] ?? null)?->isKeyword('AND') !== true) {
                throw new UnsupportedSqlException($originalSql, 'Malformed MERGE WHEN condition');
            }
            $conditionSql = trim(substr(
                $clauseSql,
                $tokens[$index]->endOffset(),
                $tokens[$thenIndex]->offset - $tokens[$index]->endOffset(),
            ));
            if ($conditionSql === '') {
                throw new UnsupportedSqlException($originalSql, 'MERGE WHEN condition cannot be empty');
            }
        }

        $actionSql = trim(substr($clauseSql, $tokens[$thenIndex]->endOffset()));
        $actionTokens = SqlTokenStream::tokenize($actionSql)->significantTokens();
        $first = $actionTokens[0] ?? null;
        if ($first?->isKeyword('DO') === true
            && ($actionTokens[1] ?? null)?->isKeyword('NOTHING') === true
            && count($actionTokens) === 2
        ) {
            return new PgSqlMergeClause($matchKind, $conditionSql, PgSqlMergeActionKind::DoNothing);
        }
        if ($matchKind === PgSqlMergeMatchKind::Matched && $first?->isKeyword('DELETE') === true && count($actionTokens) === 1) {
            return new PgSqlMergeClause($matchKind, $conditionSql, PgSqlMergeActionKind::Delete);
        }
        if ($matchKind === PgSqlMergeMatchKind::Matched && $first?->isKeyword('UPDATE') === true) {
            return new PgSqlMergeClause(
                $matchKind,
                $conditionSql,
                PgSqlMergeActionKind::Update,
                $this->parseAssignments($originalSql, $actionSql, $actionTokens),
            );
        }
        if ($matchKind === PgSqlMergeMatchKind::NotMatched && $first?->isKeyword('INSERT') === true) {
            $insert = $this->parseInsert($originalSql, $actionSql, $actionTokens);

            return new PgSqlMergeClause(
                $matchKind,
                $conditionSql,
                PgSqlMergeActionKind::Insert,
                [],
                $insert['columns'],
                $insert['values'],
            );
        }

        throw new UnsupportedSqlException($originalSql, 'MERGE action is not supported');
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array<string, string>
     */
    private function parseAssignments(string $originalSql, string $actionSql, array $tokens): array
    {
        if (($tokens[1] ?? null)?->isKeyword('SET') !== true) {
            throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE requires SET');
        }
        $setSql = trim(substr($actionSql, $tokens[1]->endOffset()));
        $assignments = [];
        foreach (SqlTokenStream::tokenize($setSql)->splitTopLevel() as $assignmentSql) {
            $assignmentTokens = SqlTokenStream::tokenize($assignmentSql)->significantTokens();
            $equals = [];
            foreach ($assignmentTokens as $assignmentIndex => $token) {
                if ($this->isSymbol($token, '=') && $token->isTopLevel()) {
                    $equals[] = $assignmentIndex;
                }
            }
            if (count($equals) !== 1) {
                throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE requires simple column assignments');
            }
            $equalsIndex = $equals[0];
            if ($equalsIndex !== 1 || count($assignmentTokens) < 3) {
                throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE requires simple column assignments');
            }
            $column = $this->identifierName($assignmentTokens[0]);
            if ($column === null) {
                throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE target must be a column');
            }
            $value = trim(substr($assignmentSql, $assignmentTokens[$equalsIndex]->endOffset()));
            if ($value === '') {
                throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE value cannot be empty');
            }
            if (array_key_exists($column, $assignments)) {
                throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE cannot assign a column more than once');
            }
            $assignments[$column] = $value;
        }
        if ($assignments === []) {
            throw new UnsupportedSqlException($originalSql, 'MERGE UPDATE requires assignments');
        }

        return $assignments;
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{columns: list<string>, values: list<string>}
     */
    private function parseInsert(string $originalSql, string $actionSql, array $tokens): array
    {
        $index = 1;
        $columns = [];
        if ($this->isSymbol($tokens[$index] ?? null, '(')) {
            $list = $this->parenthesizedList($originalSql, $actionSql, $tokens, $index);
            foreach ($list['items'] as $columnSql) {
                $columnTokens = SqlTokenStream::tokenize($columnSql)->significantTokens();
                $column = count($columnTokens) === 1 ? $this->identifierName($columnTokens[0]) : null;
                if ($column === null) {
                    throw new UnsupportedSqlException($originalSql, 'MERGE INSERT columns must be identifiers');
                }
                if (in_array($column, $columns, true)) {
                    throw new UnsupportedSqlException($originalSql, 'MERGE INSERT cannot name a column more than once');
                }
                $columns[] = $column;
            }
            $index = $list['next'];
        }

        if (($tokens[$index] ?? null)?->isKeyword('DEFAULT') === true
            && ($tokens[$index + 1] ?? null)?->isKeyword('VALUES') === true
            && $index + 2 === count($tokens)
        ) {
            if ($columns !== []) {
                throw new UnsupportedSqlException($originalSql, 'MERGE INSERT DEFAULT VALUES cannot name columns');
            }

            return ['columns' => [], 'values' => []];
        }
        if (($tokens[$index] ?? null)?->isKeyword('VALUES') !== true
            || !$this->isSymbol($tokens[$index + 1] ?? null, '(')
        ) {
            throw new UnsupportedSqlException($originalSql, 'MERGE INSERT requires VALUES');
        }

        $values = $this->parenthesizedList($originalSql, $actionSql, $tokens, $index + 1);
        if ($values['next'] !== count($tokens)) {
            throw new UnsupportedSqlException($originalSql, 'Malformed MERGE INSERT values');
        }
        if ($columns !== [] && count($columns) !== count($values['items'])) {
            throw new UnsupportedSqlException($originalSql, 'MERGE INSERT values count does not match column count');
        }

        return ['columns' => $columns, 'values' => $values['items']];
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{items: list<string>, next: int}
     */
    private function parenthesizedList(
        string $originalSql,
        string $sql,
        array $tokens,
        int $openIndex,
    ): array {
        $open = $tokens[$openIndex];
        foreach ($tokens as $closeIndex => $token) {
            if ($closeIndex <= $openIndex
                || !$this->isSymbol($token, ')')
                || $token->depth !== $open->depth
            ) {
                continue;
            }
            $listSql = trim(substr($sql, $open->endOffset(), $token->offset - $open->endOffset()));
            $items = $listSql === '' ? [] : SqlTokenStream::tokenize($listSql)->splitTopLevel();

            return ['items' => $items, 'next' => $closeIndex + 1];
        }

        throw new UnsupportedSqlException($originalSql, 'Malformed MERGE parenthesized list');
    }

    /** @param list<SqlToken> $tokens */
    private function keywordIndex(array $tokens, string $keyword, int $start): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($index >= $start && $token->isTopLevel() && $token->isKeyword($keyword)) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<SqlToken> $tokens */
    private function lastKeywordIndex(array $tokens, string $keyword, int $start, int $end): ?int
    {
        $found = null;
        foreach ($tokens as $index => $token) {
            if ($index >= $end) {
                break;
            }
            if ($index >= $start && $token->isTopLevel() && $token->isKeyword($keyword)) {
                $found = $index;
            }
        }

        return $found;
    }

    /**
     * @param list<SqlToken> $tokens
     * @return list<int>
     */
    private function mergeWhenIndices(array $tokens, int $start): array
    {
        $indices = [];
        $caseDepth = 0;
        foreach ($tokens as $index => $token) {
            if ($index < $start || !$token->isTopLevel()) {
                continue;
            }
            if ($token->isKeyword('CASE')) {
                $caseDepth++;
                continue;
            }
            if ($token->isKeyword('END') && $caseDepth > 0) {
                $caseDepth--;
                continue;
            }
            if ($caseDepth !== 0 || !$token->isKeyword('WHEN')) {
                continue;
            }
            $next = $tokens[$index + 1] ?? null;
            $afterNext = $tokens[$index + 2] ?? null;
            if ($next?->isKeyword('MATCHED') === true
                || ($next?->isKeyword('NOT') === true && $afterNext?->isKeyword('MATCHED') === true)
            ) {
                $indices[] = $index;
            }
        }

        return $indices;
    }

    /** @param list<SqlToken> $tokens */
    private function keywordIndexOutsideCase(array $tokens, string $keyword, int $start): ?int
    {
        $caseDepth = 0;
        foreach ($tokens as $index => $token) {
            if ($index < $start || !$token->isTopLevel()) {
                continue;
            }
            if ($token->isKeyword('CASE')) {
                $caseDepth++;
                continue;
            }
            if ($token->isKeyword('END') && $caseDepth > 0) {
                $caseDepth--;
                continue;
            }
            if ($caseDepth === 0 && $token->isKeyword($keyword)) {
                return $index;
            }
        }

        return null;
    }

    private function identifierName(SqlToken $token): ?string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return strtolower($token->text);
        }
        if ($token->kind !== SqlTokenKind::QuotedIdentifier || strlen($token->text) <= 2) {
            return null;
        }

        return str_replace('""', '"', substr($token->text, 1, -1));
    }

    private function isSymbol(?SqlToken $token, string $symbol): bool
    {
        return $token instanceof SqlToken
            && $token->kind === SqlTokenKind::Symbol
            && $token->text === $symbol;
    }
}
