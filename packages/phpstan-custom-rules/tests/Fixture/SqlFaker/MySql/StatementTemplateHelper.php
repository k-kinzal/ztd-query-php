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

    public function lexicalSequence(): string
    {
        return implode(' ', ['SELECT', 'FROM', 'WHERE']);
    }

    public function diagnostic(): string
    {
        return 'Cannot select a lexical profile from the catalog.';
    }

    public function multiwordTerminal(): string
    {
        return 'WITH ROLLUP';
    }
}
