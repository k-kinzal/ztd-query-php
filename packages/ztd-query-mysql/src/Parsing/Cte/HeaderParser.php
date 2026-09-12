<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Cte;

use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Header Parser.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class HeaderParser
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly SqlLexerProfile $lexerProfile)
    {
    }
    /**
     * @return array{names: list<string>, statementOffset: int|null}
     */
    public function parseHeader(string $sql): array
    {
        $tokens = $this->topLevelTokens($sql);
        if (($tokens[0] ?? null)?->isKeyword('WITH') !== true) {
            return ['names' => [], 'statementOffset' => null];
        }

        $index = 1;
        if (($tokens[$index] ?? null)?->isKeyword('RECURSIVE') === true) {
            $index++;
        }

        $names = [];
        while (isset($tokens[$index])) {
            $name = $this->identifierName($tokens[$index]);
            if ($name === null) {
                break;
            }
            $index++;

            $asIndex = $this->findAsIndex($tokens, $index);
            $index = ($asIndex ?? count($tokens)) + 1;

            if (($tokens[$index] ?? null)?->isKeyword('NOT') === true) {
                $index++;
            }
            if (($tokens[$index] ?? null)?->isKeyword('MATERIALIZED') === true) {
                $index++;
            }

            $opening = $tokens[$index] ?? null;
            $closing = $tokens[$index + 1] ?? null;
            if (!$this->isSymbol($opening, '(') || !$this->isSymbol($closing, ')')) {
                return ['names' => $names, 'statementOffset' => null];
            }
            $names[] = strtolower($name);
            $index += 2;

            $separator = $tokens[$index] ?? null;
            if (!$this->isSymbol($separator, ',')) {
                break;
            }
            $index++;
        }

        return ['names' => $names, 'statementOffset' => ($tokens[$index] ?? null)?->offset];
    }

    /**
     * @param list<SqlToken> $tokens
     */
    public function findAsIndex(array $tokens, int $start): ?int
    {
        for ($index = $start; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];
            if ($token->isKeyword('AS')) {
                return $index;
            }
            if ($token->kind === SqlTokenKind::Word) {
                return null;
            }
        }

        return null;
    }

    /**
     * Identifier Name for the supplied MySQL input.
     */
    public function identifierName(SqlToken $token): ?string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return $token->text;
        }
        if ($token->kind !== SqlTokenKind::QuotedIdentifier || strlen($token->text) < 2) {
            return null;
        }

        $quote = $token->text[0];
        $inner = substr($token->text, 1, -1);

        return str_replace($quote . $quote, $quote, $inner);
    }
    /**
     * Test a potentially absent delimiter token.
     */
    public function isSymbol(?SqlToken $token, string $symbol): bool
    {
        return $token?->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
    /**
     * @return list<SqlToken>
     */
    public function topLevelTokens(string $sql): array
    {
        $tokens = [];
        foreach (SqlTokenStream::tokenize($sql, $this->lexerProfile)->significantTokens() as $token) {
            if ($token->isTopLevel()) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    /**
     * Split a validated WITH header into its original prefix and CTE definitions.
     *
     * @return array{leading: string, recursive: bool, body: string}
     */
    public function contents(string $sql, int $statementOffset): array
    {
        $tokens = SqlTokenStream::tokenize($sql, $this->lexerProfile)->significantTokens();
        $with = $tokens[0];
        $next = $tokens[1] ?? null;
        $recursive = $next?->isKeyword('RECURSIVE') === true;
        $content = $recursive ? $next : $with;
        return [
            'leading' => substr($sql, 0, $with->offset),
            'recursive' => $recursive,
            'body' => trim(substr($sql, $content->endOffset(), $statementOffset - $content->endOffset())),
        ];
    }

}
