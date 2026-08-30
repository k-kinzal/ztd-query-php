<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Directive;

use Override;
use SqlFaker\Grammar\Source\Bison\Ast\BisonDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexeme;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%start`, which names the rule a derivation begins from.
 */
final class StartDirectiveReader implements BisonDirectiveReader
{
    /**
     * Reports whether the directive is `%start`.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%start"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%start';
    }

    /**
     * Consumes the rule name.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no rule name follows
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        unset($directive);

        $symbol = $stream->nextIf(BisonLexeme::Identifier)?->asString();

        return $symbol === null ? null : new BisonStartDeclaration($symbol);
    }
}
