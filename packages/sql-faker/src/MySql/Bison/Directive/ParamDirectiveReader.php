<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use Override;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonParamDeclaration;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%parse-param` and `%lex-param`, which add arguments to the generated
 * parser and scanner.
 *
 * The argument is host-language code in braces, so it arrives as one action
 * token rather than as a sequence of symbols.
 *
 * @visibility root
 */
final class ParamDirectiveReader implements BisonDirectiveReader
{
    /**
     * Reports whether the directive declares an extra parser or scanner argument.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%parse-param" and "%lex-param"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%parse-param' || $directive === '%lex-param';
    }

    /**
     * Consumes the braced argument declaration.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no braced code follows
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        $code = $stream->nextIf(BisonLexeme::Action)?->asString();
        if ($code === null) {
            return null;
        }

        /** @var 'parse-param'|'lex-param' $kind */
        $kind = substr($directive, 1);

        return new BisonParamDeclaration($kind, $code);
    }
}
