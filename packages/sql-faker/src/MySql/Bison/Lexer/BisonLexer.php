<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use RuntimeException;

/**
 * A minimal lexer for GNU Bison/Yacc grammar files.
 *
 * Tokenizes directives, identifiers, literals, rule delimiters,
 * prologues (%{ %}) and brace blocks ({ }) while skipping C/C++ comments.
 */
final class BisonLexer
{
    private string $input;
    private int $length;
    private int $pos = 0;

    /**
     * Small lookahead buffer for parser peeking.
     *
     * @var list<BisonToken>
     */
    private array $buffer = [];

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }

    public function next(): BisonToken
    {
        if ($this->buffer !== []) {
            /** @var BisonToken $tok */
            $tok = array_shift($this->buffer);
            return $tok;
        }

        return $this->token();
    }

    public function peek(): BisonToken
    {
        return $this->peekN(1);
    }

    public function peekN(int $n): BisonToken
    {
        if ($n < 1) {
            throw new RuntimeException('peekN($n) requires $n >= 1');
        }

        while (count($this->buffer) < $n) {
            $this->buffer[] = $this->token();
        }

        return $this->buffer[$n - 1];
    }

    private function peekChar(): ?string
    {
        $next = $this->pos + 1;
        if ($next >= $this->length) {
            return null;
        }
        return $this->input[$next];
    }

    private function readWhitespace(): string
    {
        return $this->readWhile(static fn (string $c): bool => ctype_space($c));
    }

    private function readQuoted(string $quote): string
    {
        $this->pos++; // consume opening quote
        $buf = '';

        while ($this->pos < $this->length) {
            $ch = $this->input[$this->pos];

            if ($ch === '\\') {
                $this->pos++;
                if ($this->pos < $this->length) {
                    $buf .= $this->input[$this->pos];
                    $this->pos++;
                }
                continue;
            }

            if ($ch === $quote) {
                $this->pos++;
                break;
            }

            $buf .= $ch;
            $this->pos++;
        }

        return $buf;
    }

    private function readLineComment(): string
    {
        $this->pos += 2;
        return $this->readWhile(static fn (string $c): bool => $c !== "\n");
    }

    private function readBlockComment(): string
    {
        $this->pos += 2;
        $start = $this->pos;
        while ($this->pos < $this->length) {
            if ($this->input[$this->pos] === '*' && $this->peekChar() === '/') {
                $end = $this->pos;
                $this->pos += 2;
                return substr($this->input, $start, $end - $start);
            }
            $this->pos++;
        }
        return substr($this->input, $start);
    }

    /**
     * @param callable(string): bool $pred
     */
    private function readWhile(callable $pred): string
    {
        $start = $this->pos;
        while ($this->pos < $this->length && $pred($this->input[$this->pos])) {
            $this->pos++;
        }
        return substr($this->input, $start, $this->pos - $start);
    }

    private function token(): BisonToken
    {
        $this->readWhitespace();

        if ($this->pos >= $this->length) {
            return new BisonToken(BisonTokenType::Eof, '', $this->pos);
        }

        $offset = $this->pos;
        $ch = $this->input[$this->pos];

        return match (true) {
            $ch === '/' => $this->tokenSlash($offset),
            $ch === '%' => $this->tokenPercent($offset),
            $ch === '{' => $this->tokenBrace($offset),
            $ch === '<' => $this->tokenTypeTag($offset),
            $ch === ':' => $this->tokenColon($offset),
            $ch === ';' => $this->tokenSemicolon($offset),
            $ch === '|' => $this->tokenPipe($offset),
            $ch === '\'' => $this->tokenCharLiteral($offset),
            $ch === '"' => $this->tokenStringLiteral($offset),
            ctype_digit($ch) => $this->tokenNumber($offset),
            preg_match('/[A-Za-z_]/', $ch) === 1 => $this->tokenIdentifier($offset),
            default => throw new RuntimeException("Unexpected character '{$ch}' at offset {$offset}"),
        };
    }

    private function tokenSlash(int $offset): BisonToken
    {
        return match ($this->peekChar()) {
            '/' => $this->tokenLineComment(),
            '*' => $this->tokenBlockComment(),
            default => throw new RuntimeException("Unexpected '/' at offset {$offset}"),
        };
    }

    private function tokenLineComment(): BisonToken
    {
        $this->readLineComment();
        return $this->token();
    }

    private function tokenBlockComment(): BisonToken
    {
        $this->readBlockComment();
        return $this->token();
    }

    private function tokenPercent(int $offset): BisonToken
    {
        return match ($this->peekChar()) {
            '%' => $this->tokenPercentPercent($offset),
            '{' => $this->tokenPrologue($offset),
            default => $this->tokenDirective($offset),
        };
    }

    private function tokenPercentPercent(int $offset): BisonToken
    {
        $this->pos += 2;
        return new BisonToken(BisonTokenType::PercentPercent, '%%', $offset);
    }

    private function tokenPrologue(int $offset): BisonToken
    {
        $this->pos += 2;
        $start = $this->pos;
        $end = strpos($this->input, '%}', $this->pos);
        if ($end === false) {
            $content = substr($this->input, $start);
            $this->pos = $this->length;
        } else {
            $content = substr($this->input, $start, $end - $start);
            $this->pos = $end + 2;
        }
        return new BisonToken(BisonTokenType::Prologue, $content, $offset);
    }

    private function tokenDirective(int $offset): BisonToken
    {
        $this->pos++;
        $name = $this->readWhile(static fn (string $c): bool => (bool) preg_match('/[A-Za-z0-9_.-]/', $c));
        if ($name === '') {
            throw new RuntimeException("Unexpected '%' at offset {$offset}");
        }
        return new BisonToken(BisonTokenType::Directive, '%' . $name, $offset);
    }

    private function tokenBrace(int $offset): BisonToken
    {
        $start = $this->pos + 1;
        $depth = 0;
        $terminated = false;

        while ($this->pos < $this->length) {
            $ch = $this->input[$this->pos];

            if ($ch === '/' && $this->peekChar() === '/') {
                $this->readLineComment();
            } elseif ($ch === '/' && $this->peekChar() === '*') {
                $this->readBlockComment();
            } elseif ($ch === '"' || $ch === '\'') {
                $this->readQuoted($ch);
            } elseif ($ch === '{') {
                $depth++;
                $this->pos++;
            } elseif ($ch === '}') {
                $depth--;
                $this->pos++;
                if ($depth <= 0) {
                    $terminated = true;
                    break;
                }
            } else {
                $this->pos++;
            }
        }

        $end = $terminated ? $this->pos - 1 : $this->pos;
        $content = substr($this->input, $start, $end - $start);
        return new BisonToken(BisonTokenType::Action, $content, $offset);
    }

    private function tokenTypeTag(int $offset): BisonToken
    {
        $this->pos++;
        $tag = $this->readWhile(static fn (string $c): bool => $c !== '>');
        if ($this->pos >= $this->length || $this->input[$this->pos] !== '>') {
            throw new RuntimeException("Unterminated type tag starting at offset {$offset}");
        }
        $this->pos++;
        return new BisonToken(BisonTokenType::TypeTag, trim($tag), $offset);
    }

    private function tokenColon(int $offset): BisonToken
    {
        $this->pos++;
        return new BisonToken(BisonTokenType::Colon, ':', $offset);
    }

    private function tokenSemicolon(int $offset): BisonToken
    {
        $this->pos++;
        return new BisonToken(BisonTokenType::Semicolon, ';', $offset);
    }

    private function tokenPipe(int $offset): BisonToken
    {
        $this->pos++;
        return new BisonToken(BisonTokenType::Pipe, '|', $offset);
    }

    private function tokenCharLiteral(int $offset): BisonToken
    {
        $value = $this->readQuoted('\'');
        return new BisonToken(BisonTokenType::CharLiteral, $value, $offset);
    }

    private function tokenStringLiteral(int $offset): BisonToken
    {
        $value = $this->readQuoted('"');
        return new BisonToken(BisonTokenType::StringLiteral, $value, $offset);
    }

    private function tokenNumber(int $offset): BisonToken
    {
        $num = $this->readWhile(static fn (string $c): bool => ctype_digit($c));
        return new BisonToken(BisonTokenType::Number, (int) $num, $offset);
    }

    private function tokenIdentifier(int $offset): BisonToken
    {
        $ident = $this->readWhile(static fn (string $c): bool => preg_match('/[A-Za-z0-9_.]/', $c) === 1);
        return new BisonToken(BisonTokenType::Identifier, $ident, $offset);
    }
}
