<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

final class StatementTemplateHelper
{
    public function statement(): string
    {
        return 'SELECT id FROM users';
    }

    public function splitStatement(): string
    {
        return 'SEL' . 'ECT id FROM users';
    }

    public function parenthesizedStatement(): string
    {
        return '(SELECT id FROM users)';
    }

    public function statementAfterComment(): string
    {
        return '/* fixture */ SELECT id FROM users';
    }

    public function embeddedStatement(): string
    {
        return 'value = (SELECT id FROM users)';
    }

    public function clauseFragment(): string
    {
        return ' FROM users WHERE id = 1';
    }

    public function joinedStatement(string $columns, string $table): string
    {
        return implode(' ', ['SELECT', $columns, 'FROM', $table]);
    }

    public function partialStatement(string $columns): string
    {
        return 'SELECT ' . $columns . ' FROM users';
    }

    public function formattedStatement(string $columns, string $table): string
    {
        return sprintf('SELECT %s FROM %s', $columns, $table);
    }

    public function accumulatedStatement(): string
    {
        $sql = 'SELECT id';
        $sql .= ' FROM users';

        return $sql;
    }

    public function assignedConcatStatement(): string
    {
        $sql = 'SELECT id';
        $sql = $sql . ' FROM users';

        return $sql;
    }

    public function splitFormattedStatement(string $columns, string $table): string
    {
        return sprintf('%s %s FROM %s', 'SELECT', $columns, $table);
    }

    public function splitFormattedArrayStatement(string $columns, string $table): string
    {
        return vsprintf('%s %s FROM %s', ['SELECT', $columns, $table]);
    }

    public function dynamicallyJoinedStatement(string $columns, string $table): string
    {
        $separator = ' ';

        return implode($separator, ['SELECT', $columns, 'FROM', $table]);
    }

    public function hoistedTokenStatement(): string
    {
        $tokens = ['INSERT', 'INTO', 'users', '(', 'id', ')', 'VALUES', '(', '1', ')'];

        return implode(' ', $tokens);
    }

    public function replacedTokenStatement(): string
    {
        return str_replace('_', ' ', 'SELECT_id_FROM_users');
    }

    public function anonymousStatementFactory(): object
    {
        return new class () {
            public function statement(): string
            {
                return 'SELECT id FROM users';
            }
        };
    }

    public function diagnostic(): string
    {
        return 'Cannot select a lexical profile from the catalog.';
    }

    public function multiwordTerminal(): string
    {
        return 'WITH ROLLUP';
    }

    /** @return list<string> */
    public function lexicalCatalog(): array
    {
        return ['SELECT', 'FROM', 'WHERE'];
    }

    public function plainSelectList(): string
    {
        return 'SELECT id, name FROM users';
    }

    public function recursiveCommonTableExpression(): string
    {
        return 'WITH RECURSIVE t(n) AS (SELECT n FROM q) SELECT n FROM t';
    }

    public function statementAfterLineComment(): string
    {
        return "-- fixture\nSELECT id FROM users";
    }

    public function transactionStatement(): string
    {
        return 'COMMIT';
    }
}
