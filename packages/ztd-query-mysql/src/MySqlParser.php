<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Token;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * MySQL parser implementation backed by phpMyAdmin SQL parser.
 */
final class MySqlParser
{
    /**
     * Parse SQL into an array of statements with PHP 8.5+ compatibility.
     *
     * Suppresses warnings for large number to int conversion that occur
     * in phpmyadmin/sql-parser when parsing SQL with very large numeric literals.
     *
     * @return list<Statement>
     */
    public function parse(string $sql): array
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            if ($errno === E_WARNING && str_contains($errstr, 'is not representable as an int')) {
                return true;
            }
            return false;
        });

        try {
            $parser = new Parser($this->normalizeOptionalInsertInto($sql));
            return array_values($parser->statements);
        } finally {
            restore_error_handler();
        }
    }

    /** @return list<string> */
    public function splitStatements(string $sql): array
    {
        return SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->splitStatements();
    }

    public function parseSingleLogicalStatement(string $sql): ?Statement
    {
        if (count($this->splitStatements($sql)) !== 1) {
            return null;
        }

        $statements = $this->parse($sql);
        $first = $statements[0] ?? null;
        foreach (array_slice($statements, 1) as $continuation) {
            if (!$first instanceof SelectStatement
                && (!$first instanceof InsertStatement || $first->select === null)
            ) {
                return null;
            }
            if (!$continuation instanceof SelectStatement || $continuation->into !== null) {
                return null;
            }
        }

        return $first;
    }

    private function normalizeOptionalInsertInto(string $sql): string
    {
        $tokens = [];
        foreach (Lexer::getTokens($sql)->tokens as $token) {
            if (in_array($token->type, [Token::TYPE_WHITESPACE, Token::TYPE_COMMENT, Token::TYPE_DELIMITER], true)) {
                continue;
            }
            $tokens[] = $token;
        }

        $insert = $tokens[0] ?? null;
        if ($insert === null || $insert->keyword !== 'INSERT') {
            return $sql;
        }

        $targetIndex = 1;
        while (isset($tokens[$targetIndex]) && in_array(
            $tokens[$targetIndex]->keyword,
            ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY', 'IGNORE'],
            true,
        )) {
            $targetIndex++;
        }

        $target = $tokens[$targetIndex] ?? $insert;
        if (!in_array($target->type, [Token::TYPE_NONE, Token::TYPE_SYMBOL], true)) {
            return $sql;
        }
        if (!is_int($target->position)) {
            return $sql;
        }

        return substr($sql, 0, $target->position) . 'INTO ' . substr($sql, $target->position);
    }
}
