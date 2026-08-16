<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Shadow\ShadowTransactionManager;

/**
 * A structure-aware transaction-control statement.
 */
final class TransactionStatement
{
    private const BEGIN = 'begin';
    private const COMMIT = 'commit';
    private const ROLLBACK = 'rollback';
    private const SAVEPOINT = 'savepoint';
    private const ROLLBACK_TO = 'rollback_to';
    private const RELEASE = 'release';

    private function __construct(
        private readonly string $operation,
        private readonly ?string $savepointName = null,
    ) {
    }

    public static function parse(string $sql): ?self
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        if (($tokens[count($tokens) - 1] ?? null)?->text === ';') {
            array_pop($tokens);
        }

        if (self::keywords($tokens, ['BEGIN'])
            || self::keywords($tokens, ['BEGIN', 'TRANSACTION'])
            || self::keywords($tokens, ['BEGIN', 'DEFERRED'])
            || self::keywords($tokens, ['BEGIN', 'DEFERRED', 'TRANSACTION'])
            || self::keywords($tokens, ['BEGIN', 'IMMEDIATE'])
            || self::keywords($tokens, ['BEGIN', 'IMMEDIATE', 'TRANSACTION'])
            || self::keywords($tokens, ['BEGIN', 'EXCLUSIVE'])
            || self::keywords($tokens, ['BEGIN', 'EXCLUSIVE', 'TRANSACTION'])
            || self::keywords($tokens, ['START', 'TRANSACTION'])
        ) {
            return new self(self::BEGIN);
        }
        if (self::keywords($tokens, ['COMMIT'])
            || self::keywords($tokens, ['COMMIT', 'TRANSACTION'])
            || self::keywords($tokens, ['END'])
            || self::keywords($tokens, ['END', 'TRANSACTION'])
        ) {
            return new self(self::COMMIT);
        }
        if (self::keywords($tokens, ['ROLLBACK']) || self::keywords($tokens, ['ROLLBACK', 'TRANSACTION'])) {
            return new self(self::ROLLBACK);
        }

        $name = self::nameAfter($tokens, ['SAVEPOINT']);
        if ($name !== null) {
            return new self(self::SAVEPOINT, $name);
        }
        $name = self::nameAfter($tokens, ['ROLLBACK', 'TO', 'SAVEPOINT'])
            ?? self::nameAfter($tokens, ['ROLLBACK', 'TO']);
        if ($name !== null) {
            return new self(self::ROLLBACK_TO, $name);
        }
        $name = self::nameAfter($tokens, ['RELEASE', 'SAVEPOINT'])
            ?? self::nameAfter($tokens, ['RELEASE']);
        if ($name !== null) {
            return new self(self::RELEASE, $name);
        }

        return null;
    }

    public function apply(ShadowTransactionManager $transactions): void
    {
        match ($this->operation) {
            self::BEGIN => $transactions->begin(),
            self::COMMIT => $transactions->commit(),
            self::ROLLBACK => $transactions->rollBack(),
            self::SAVEPOINT => $transactions->savepoint($this->requiredSavepointName()),
            self::ROLLBACK_TO => $transactions->rollBackTo($this->requiredSavepointName()),
            self::RELEASE => $transactions->release($this->requiredSavepointName()),
            default => null,
        };
    }

    /**
     * @param list<SqlToken> $tokens
     * @param list<string> $keywords
     */
    private static function keywords(array $tokens, array $keywords): bool
    {
        if (count($tokens) !== count($keywords)) {
            return false;
        }
        foreach ($keywords as $index => $keyword) {
            if (!$tokens[$index]->isKeyword($keyword)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SqlToken> $tokens
     * @param list<string> $keywords
     */
    private static function nameAfter(array $tokens, array $keywords): ?string
    {
        if (count($tokens) !== count($keywords) + 1) {
            return null;
        }
        foreach ($keywords as $index => $keyword) {
            if (!$tokens[$index]->isKeyword($keyword)) {
                return null;
            }
        }

        $name = $tokens[count($keywords)];
        if (!in_array($name->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true)) {
            return null;
        }

        return self::unquote($name->text);
    }

    private static function unquote(string $identifier): string
    {
        $first = $identifier[0] ?? '';
        $last = $identifier[strlen($identifier) - 1] ?? '';
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return str_replace(']]', ']', substr($identifier, 1, -1));
        }

        return $identifier;
    }

    private function requiredSavepointName(): string
    {
        return $this->savepointName ?? '';
    }
}
