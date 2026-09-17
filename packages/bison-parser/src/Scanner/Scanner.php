<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

use BisonParser\Ast\Location;
use BisonParser\SyntaxException;

/**
 * Splits a grammar file into the tokens Bison's own scanner produces.
 *
 * This follows `scan-gram.l`: an identifier is looked past to see whether
 * a colon or a bracketed name follows, a `%name-prefix` may be followed by
 * `=`, a stray comma is whitespace, and everything after the second `%%`
 * is one epilogue token.
 *
 * @visibility root
 */
final class Scanner
{
    /**
     * Pattern of an identifier: a letter, underscore or dot, then also digits and hyphens.
     */
    public const IDENTIFIER = '[.A-Za-z_][-.A-Za-z0-9_]*';

    /**
     * @param CodeReader $code Reads host code
     * @param Escapes $escapes Decodes literals
     */
    public function __construct(
        private readonly CodeReader $code = new CodeReader(),
        private readonly Escapes $escapes = new Escapes(),
    ) {
    }

    /**
     * Tokenizes a grammar file.
     *
     * @param string $source Contents of the file
     *
     * @return list<Token> The tokens, ending with the end-of-file token
     *
     * @throws SyntaxException When the file holds something the grammar language does not allow
     */
    public function scan(string $source): array
    {
        $cursor = new Cursor($source);
        $tokens = [];
        $sections = 0;
        while (true) {
            $this->skipTrivia($cursor);
            if ($cursor->eof()) {
                $tokens[] = new Token(TokenKind::End, '', $cursor->location());

                return $tokens;
            }
            foreach ($this->next($cursor) as $token) {
                $tokens[] = $token;
            }
            $last = $tokens[count($tokens) - 1];
            if ($last->is(TokenKind::Section) && ++$sections === 2) {
                $location = $cursor->location();
                $tokens[] = new Token(TokenKind::Epilogue, $cursor->take(strlen($source)), $location);
                $tokens[] = new Token(TokenKind::End, '', $cursor->location());

                return $tokens;
            }
        }
    }

    /**
     * Skips whitespace, comments, stray commas and `#line` directives.
     *
     * @param Cursor $cursor Cursor to advance
     *
     * @throws SyntaxException When a comment never closes
     */
    public function skipTrivia(Cursor $cursor): void
    {
        while (!$cursor->eof()) {
            if ($cursor->match('[ \f\t\v\r\n,]+') !== null || $cursor->match('//[^\n]*') !== null) {
                continue;
            }
            if ($cursor->startsWith('/*')) {
                $location = $cursor->location();
                if ($cursor->takeUntil('*/') === null) {
                    throw SyntaxException::unterminated('comment', '*/', $location);
                }
                continue;
            }

            return;
        }
    }

    /**
     * Reads the token, or the tokens, that begin at the cursor.
     *
     * @param Cursor $cursor Cursor positioned on a token
     *
     * @return list<Token> One token, or an identifier and its bracketed name
     *
     * @throws SyntaxException When no token begins here or it never closes
     */
    public function next(Cursor $cursor): array
    {
        $location = $cursor->location();
        $byte = $cursor->peek();
        if ($byte === '#' && $location->column === 1) {
            $directive = $cursor->match('#line [0-9]+(?: "[^"\n]*")?(?=\r?\n)');
            if ($directive !== null) {
                return [new Token(TokenKind::Line, $directive, $location)];
            }
        }
        if ($byte === '%') {
            return [$this->percent($cursor, $location)];
        }
        if ($byte === '{') {
            return [new Token(TokenKind::Code, $this->code->braced($cursor), $location)];
        }
        if (preg_match('/[.A-Za-z_]/', $byte) === 1 && !$cursor->startsWith('_("')) {
            return $this->identifier($cursor, $location);
        }
        if (ctype_digit($byte)) {
            return [$this->integer($cursor, $location)];
        }
        $token = $this->literal($cursor, $location) ?? $this->punctuation($cursor, $location);
        if ($token === null) {
            throw SyntaxException::invalid("invalid character: '{$byte}'", $location);
        }

        return [$token];
    }

    /**
     * Reads what begins with a percent sign: a prologue, a section marker, a predicate or a directive.
     *
     * @param Cursor $cursor Cursor positioned on the percent sign
     * @param Location $location Where it is
     *
     * @return Token The token
     *
     * @throws SyntaxException When the directive is unknown or the code never closes
     */
    public function percent(Cursor $cursor, Location $location): Token
    {
        if ($cursor->startsWith('%{')) {
            return new Token(TokenKind::Prologue, $this->code->prologue($cursor), $location);
        }
        if ($cursor->startsWith('%%')) {
            $cursor->take(2);

            return new Token(TokenKind::Section, '%%', $location);
        }
        if ($cursor->match('%\?[ \f\t\v]*(?:\r?\n[ \f\t\v]*)*(?=\{)') !== null) {
            return new Token(TokenKind::Predicate, $this->code->braced($cursor), $location);
        }
        $raw = $cursor->match('%' . self::IDENTIFIER);
        $name = $raw === null ? null : Directives::canonical(substr($raw, 1));
        if ($raw === null || $name === null) {
            throw SyntaxException::invalid('invalid directive: ' . ($raw ?? '%'), $location);
        }
        if (in_array($name, Directives::EQUAL_OPTIONAL, true)) {
            $cursor->match('\s*=\s*');
        }

        return new Token(TokenKind::Directive, $name, $location, $raw);
    }

    /**
     * Reads an identifier and looks past it for a bracketed name and a colon.
     *
     * @param Cursor $cursor Cursor positioned on the identifier
     * @param Location $location Where it is
     *
     * @return list<Token> The identifier, followed by its bracketed name when one follows it
     *
     * @throws SyntaxException When a bracketed name is malformed
     */
    public function identifier(Cursor $cursor, Location $location): array
    {
        $name = $cursor->match(self::IDENTIFIER) ?? '';
        $this->skipTrivia($cursor);
        $bracketed = null;
        if ($cursor->peek() === '[') {
            $bracketed = $this->bracketed($cursor);
            $this->skipTrivia($cursor);
        }
        $kind = $cursor->peek() === ':' ? TokenKind::IdentifierColon : TokenKind::Identifier;
        $tokens = [new Token($kind, $name, $location)];
        if ($bracketed !== null) {
            $tokens[] = $bracketed;
        }

        return $tokens;
    }

    /**
     * Reads a bracketed name such as `[left]`.
     *
     * @param Cursor $cursor Cursor positioned on the opening bracket
     *
     * @return Token The bracketed identifier
     *
     * @throws SyntaxException When the brackets hold anything but one identifier
     */
    public function bracketed(Cursor $cursor): Token
    {
        $location = $cursor->location();
        $cursor->take(1);
        $this->skipTrivia($cursor);
        $name = $cursor->match(self::IDENTIFIER);
        if ($name === null) {
            throw SyntaxException::invalid('an identifier expected', $cursor->location());
        }
        $this->skipTrivia($cursor);
        if ($cursor->peek() !== ']') {
            throw SyntaxException::unexpected("']'", $cursor->eof() ? 'end of file' : "'{$cursor->peek()}'", $cursor->location());
        }
        $cursor->take(1);

        return new Token(TokenKind::BracketedIdentifier, $name, $location);
    }

    /**
     * Reads a decimal or hexadecimal integer.
     *
     * @param Cursor $cursor Cursor positioned on the first digit
     * @param Location $location Where it is
     *
     * @return Token The integer, its text in decimal
     *
     * @throws SyntaxException When letters follow the digits
     */
    public function integer(Cursor $cursor, Location $location): Token
    {
        $text = $cursor->match('0[xX][0-9A-Fa-f]+|[0-9]+') ?? '';
        if ($cursor->lookingAt('[-.A-Za-z0-9_]')) {
            throw SyntaxException::invalid('invalid identifier: ' . $text . $cursor->match('[-.A-Za-z0-9_]*'), $location);
        }
        $value = str_starts_with(strtolower($text), '0x') ? hexdec(substr($text, 2)) : (int) $text;

        return new Token(TokenKind::Integer, (string) $value, $location, $text);
    }

    /**
     * Reads a character literal, a string, a translatable string, or a tag.
     *
     * @param Cursor $cursor Cursor positioned on the literal
     * @param Location $location Where it is
     *
     * @return Token|null The token, or null when no literal begins here
     *
     * @throws SyntaxException When the literal never closes or a character literal is not one byte
     */
    public function literal(Cursor $cursor, Location $location): ?Token
    {
        $byte = $cursor->peek();
        if ($byte === "'") {
            $literal = $cursor->match("'(?:\\\\.|[^\\\\'\\n])*'");
            if ($literal === null) {
                throw SyntaxException::unterminated('character literal', "'", $location);
            }
            $decoded = $this->escapes->decode(substr($literal, 1, -1), $location);
            if ($decoded === '') {
                throw SyntaxException::invalid('empty character literal', $location);
            }
            if (strlen($decoded) !== 1) {
                throw SyntaxException::invalid('extra characters in character literal', $location);
            }

            return new Token(TokenKind::CharLiteral, $decoded, $location, $literal);
        }
        if ($byte === '"' || $cursor->startsWith('_("')) {
            $translatable = $byte === '_';
            $literal = $cursor->match($translatable ? '_\("(?:\\\\.|[^\\\\"\n])*"\)' : '"(?:\\\\.|[^\\\\"\n])*"');
            if ($literal === null) {
                throw SyntaxException::unterminated('string', '"', $location);
            }
            $body = $translatable ? substr($literal, 3, -2) : substr($literal, 1, -1);

            return new Token($translatable ? TokenKind::TranslatableString : TokenKind::String, $this->escapes->decode($body, $location), $location, $literal);
        }
        if ($byte === '<') {
            return $this->tag($cursor, $location);
        }

        return null;
    }

    /**
     * Reads a tag, which may nest angle brackets as a C++ template does.
     *
     * @param Cursor $cursor Cursor positioned on the opening angle bracket
     * @param Location $location Where it is
     *
     * @return Token The tag
     *
     * @throws SyntaxException When the tag never closes
     */
    public function tag(Cursor $cursor, Location $location): Token
    {
        if ($cursor->startsWith('<*>')) {
            $cursor->take(3);

            return new Token(TokenKind::TagAny, '*', $location);
        }
        if ($cursor->startsWith('<>')) {
            $cursor->take(2);

            return new Token(TokenKind::TagNone, '', $location);
        }
        $cursor->take(1);
        $nesting = 0;
        $text = '';
        while (!$cursor->eof()) {
            $unit = $cursor->match('(?:->|[^<>])+|<+') ?? $cursor->take(1);
            if ($unit === '>') {
                if ($nesting === 0) {
                    return new Token(TokenKind::Tag, $text, $location);
                }
                $nesting--;
            } elseif ($unit[0] === '<') {
                $nesting += strlen($unit);
            }
            $text .= $unit;
        }

        throw SyntaxException::unterminated('tag', '>', $location);
    }

    /**
     * Reads one punctuation byte.
     *
     * @param Cursor $cursor Cursor positioned on the byte
     * @param Location $location Where it is
     *
     * @return Token|null The token, or null when the byte is not punctuation the language has
     *
     * @throws SyntaxException When a bracketed name is malformed
     */
    public function punctuation(Cursor $cursor, Location $location): ?Token
    {
        $byte = $cursor->peek();
        if ($byte === '[') {
            return $this->bracketed($cursor);
        }
        $kind = match ($byte) {
            ':' => TokenKind::Colon,
            '=' => TokenKind::Equal,
            '|' => TokenKind::Pipe,
            ';' => TokenKind::Semicolon,
            default => null,
        };
        if ($kind === null) {
            return null;
        }
        $cursor->take(1);

        return new Token($kind, $byte, $location);
    }
}
