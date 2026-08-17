<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Schema\TablePartitionKey;
use ZtdQuery\Schema\TablePartitionRelation;
use ZtdQuery\Schema\TablePartitionStrategy;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class PgSqlPartitionParser
{
    public function parseKey(string $sql): ?TablePartitionKey
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $partition = $this->keywordPairIndex($tokens, 'PARTITION', 'BY');
        if ($partition === null) {
            return null;
        }

        $strategyToken = $tokens[$partition + 2] ?? null;
        if (!$strategyToken instanceof SqlToken) {
            return null;
        }
        $strategy = match (true) {
            $strategyToken->isKeyword('RANGE') => TablePartitionStrategy::Range,
            $strategyToken->isKeyword('LIST') => TablePartitionStrategy::List,
            $strategyToken->isKeyword('HASH') => TablePartitionStrategy::Hash,
            default => null,
        };
        if ($strategy === null) {
            return null;
        }

        $openIndex = $partition + 3;
        $closeIndex = $this->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            return null;
        }
        $open = $tokens[$openIndex];
        $close = $tokens[$closeIndex];
        $body = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $expressions = SqlTokenStream::tokenize($body)->splitTopLevel();
        if ($expressions === [] || in_array('', $expressions, true)) {
            return null;
        }

        return new TablePartitionKey($strategy, $expressions);
    }

    public function parentTable(string $sql): ?string
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $partition = $this->keywordPairIndex($tokens, 'PARTITION', 'OF');
        if ($partition === null) {
            return null;
        }

        return $this->identifierAt($tokens, $partition + 2)['name'] ?? null;
    }

    public function parseRelation(string $sql, TablePartitionKey $parentKey): ?TablePartitionRelation
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $partition = $this->keywordPairIndex($tokens, 'PARTITION', 'OF');
        if ($partition === null) {
            return null;
        }
        $parent = $this->identifierAt($tokens, $partition + 2);
        if ($parent === null) {
            return null;
        }

        $defaultIndex = $this->keywordIndex($tokens, 'DEFAULT', $parent['next']);
        $valuesIndex = $this->keywordPairIndex($tokens, 'FOR', 'VALUES', $parent['next']);
        if ($defaultIndex !== null && ($valuesIndex === null || $defaultIndex < $valuesIndex)) {
            return new TablePartitionRelation($parent['name'], null);
        }
        if ($valuesIndex === null) {
            return null;
        }

        $predicate = match ($parentKey->strategy) {
            TablePartitionStrategy::Range => $this->rangePredicate($sql, $tokens, $valuesIndex + 2, $parentKey),
            TablePartitionStrategy::List => $this->listPredicate($sql, $tokens, $valuesIndex + 2, $parentKey),
            TablePartitionStrategy::Hash => null,
        };

        return $predicate === null ? null : new TablePartitionRelation($parent['name'], $predicate);
    }

    /** @param list<SqlToken> $tokens */
    private function rangePredicate(string $sql, array $tokens, int $index, TablePartitionKey $key): ?string
    {
        $from = $tokens[$index] ?? null;
        if (!$from instanceof SqlToken || !$from->isKeyword('FROM')) {
            return null;
        }
        $lower = $this->parenthesizedValues($sql, $tokens, $index + 1);
        if ($lower === null) {
            return null;
        }

        $to = $tokens[$lower['next']] ?? null;
        if (!$to instanceof SqlToken || !$to->isKeyword('TO')) {
            return null;
        }
        $upper = $this->parenthesizedValues($sql, $tokens, $lower['next'] + 1);
        if ($upper === null) {
            return null;
        }

        $lowerPredicate = $this->rangeBoundary($key->expressions, $lower['values'], '>=', 'MINVALUE');
        $upperPredicate = $this->rangeBoundary($key->expressions, $upper['values'], '<', 'MAXVALUE');
        if ($lowerPredicate === false || $upperPredicate === false) {
            return null;
        }

        $predicates = array_values(array_filter(
            [$lowerPredicate, $upperPredicate],
            static fn (string|false|null $predicate): bool => is_string($predicate),
        ));

        return $predicates === [] ? 'TRUE' : implode(' AND ', $predicates);
    }

    /** @param list<SqlToken> $tokens */
    private function listPredicate(string $sql, array $tokens, int $index, TablePartitionKey $key): ?string
    {
        if (count($key->expressions) !== 1) {
            return null;
        }
        $in = $tokens[$index] ?? null;
        if (!$in instanceof SqlToken || !$in->isKeyword('IN')) {
            return null;
        }
        $values = $this->parenthesizedValues($sql, $tokens, $index + 1);
        if ($values === null) {
            return null;
        }

        $nonNull = [];
        $hasNull = false;
        foreach ($values['values'] as $value) {
            $valueTokens = SqlTokenStream::tokenize($value)->significantTokens();
            if (count($valueTokens) === 1 && $valueTokens[0]->isKeyword('NULL')) {
                $hasNull = true;
                continue;
            }
            $nonNull[] = $value;
        }
        if ($nonNull === [] && !$hasNull) {
            return null;
        }

        $expression = '(' . $key->expressions[0] . ')';
        $predicate = $nonNull === [] ? null : $expression . ' IN (' . implode(', ', $nonNull) . ')';
        if ($hasNull) {
            $nullPredicate = "$expression IS NULL";
            $predicate = $predicate === null ? $nullPredicate : "($predicate OR $nullPredicate)";
        }

        return $predicate;
    }

    /**
     * @param non-empty-list<string> $expressions
     * @param list<string> $values
     */
    private function rangeBoundary(array $expressions, array $values, string $operator, string $unbounded): string|false|null
    {
        if (count($expressions) !== count($values)) {
            return false;
        }

        $special = [];
        foreach ($values as $value) {
            $special[] = in_array(strtoupper(trim($value)), ['MINVALUE', 'MAXVALUE'], true);
        }
        if (!in_array(false, $special, true)) {
            foreach ($values as $value) {
                if (strcasecmp(trim($value), $unbounded) !== 0) {
                    return false;
                }
            }

            return null;
        }
        if (in_array(true, $special, true)) {
            return false;
        }

        if (count($expressions) === 1) {
            return '(' . $expressions[0] . ") $operator " . $values[0];
        }

        return 'ROW(' . implode(', ', $expressions) . ") $operator ROW(" . implode(', ', $values) . ')';
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{values: list<string>, next: int}|null
     */
    private function parenthesizedValues(string $sql, array $tokens, int $openIndex): ?array
    {
        $closeIndex = $this->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            return null;
        }
        $open = $tokens[$openIndex];
        $close = $tokens[$closeIndex];
        $body = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $values = SqlTokenStream::tokenize($body)->splitTopLevel();
        if ($values === [] || in_array('', $values, true)) {
            return null;
        }

        return ['values' => $values, 'next' => $closeIndex + 1];
    }

    /** @param list<SqlToken> $tokens */
    private function closingParenthesisIndex(array $tokens, int $openIndex): ?int
    {
        $open = $tokens[$openIndex] ?? null;
        if (!$open instanceof SqlToken || !$this->isSymbol($open, '(')) {
            return null;
        }
        foreach (array_slice($tokens, $openIndex + 1, null, true) as $index => $token) {
            if ($this->isSymbol($token, ')') && $token->depth === $open->depth) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{name: string, next: int}|null
     */
    private function identifierAt(array $tokens, int $index): ?array
    {
        $token = $tokens[$index] ?? null;
        $name = $token instanceof SqlToken ? $this->identifierName($token) : null;
        if ($name === null) {
            return null;
        }
        $index++;

        while ($this->isSymbol($tokens[$index] ?? null, '.')) {
            $component = $tokens[$index + 1] ?? null;
            $componentName = $component instanceof SqlToken ? $this->identifierName($component) : null;
            if ($componentName === null) {
                return null;
            }
            $name = $componentName;
            $index += 2;
        }

        return ['name' => $name, 'next' => $index];
    }

    /** @param list<SqlToken> $tokens */
    private function keywordPairIndex(array $tokens, string $first, string $second, int $start = 0): ?int
    {
        foreach (array_slice($tokens, $start, null, true) as $index => $token) {
            if ($token->isTopLevel()
                && $token->isKeyword($first)
                && ($tokens[$index + 1] ?? null)?->isKeyword($second) === true
            ) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<SqlToken> $tokens */
    private function keywordIndex(array $tokens, string $keyword, int $start): ?int
    {
        foreach (array_slice($tokens, $start, null, true) as $index => $token) {
            if ($token->isTopLevel() && $token->isKeyword($keyword)) {
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
        if ($token->kind !== SqlTokenKind::QuotedIdentifier || strlen($token->text) < 2) {
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
