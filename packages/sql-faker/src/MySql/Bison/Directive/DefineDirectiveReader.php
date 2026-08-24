<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use Override;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%define`, which sets a named option of the generated parser.
 *
 * The value is optional: `%define api.pure` is a flag, while
 * `%define api.prefix "yy"` carries a setting. A name on its own is therefore
 * not an error, and the value is only taken when something that can be one
 * follows.
 */
final class DefineDirectiveReader implements BisonDirectiveReader
{
    /**
     * Reports whether the directive is `%define`.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%define"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%define';
    }

    /**
     * Consumes the option name and, when present, its value.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no option name follows
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        unset($directive);

        $name = $stream->nextIf(BisonLexeme::Identifier)?->asString();
        if ($name === null) {
            return null;
        }

        $value = $stream->nextIf(
            BisonLexeme::Identifier,
            BisonLexeme::StringLiteral,
            BisonLexeme::Number,
        )?->asString();

        return new BisonDefineDeclaration($name, $value);
    }
}
