<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use Override;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%expect`, the count of shift/reduce conflicts the author accepts.
 */
final class ExpectDirectiveReader implements BisonDirectiveReader
{
    /**
     * Reports whether the directive is `%expect`.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%expect"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%expect';
    }

    /**
     * Consumes the conflict count.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no number follows
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        unset($directive);

        $count = $stream->nextIf(BisonLexeme::Number)?->asInt();

        return $count === null ? null : new BisonExpectDeclaration($count);
    }
}
