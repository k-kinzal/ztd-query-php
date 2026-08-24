<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use Override;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%type`, which gives rules the semantic type their values carry.
 *
 * The type tag is what the directive exists for, so a `%type` without one
 * declares nothing and is reported as absent rather than as an empty list.
 */
final class TypeDirectiveReader implements BisonDirectiveReader
{
    /** @readonly */
    private BisonDeclarationBoundary $boundary;

    /**
     * @param BisonDeclarationBoundary|null $boundary Decides where the declaration ends
     */
    public function __construct(?BisonDeclarationBoundary $boundary = null)
    {
        $this->boundary = $boundary ?? new BisonDeclarationBoundary();
    }

    /**
     * Reports whether the directive is `%type`.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%type"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%type';
    }

    /**
     * Consumes the type tag and the rule names it applies to.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no type tag follows
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        unset($directive);

        $typeTag = $stream->nextIf(BisonLexeme::TypeTag)?->asString();
        if ($typeTag === null) {
            return null;
        }

        /** @var list<string> $symbols */
        $symbols = [];
        while ($this->boundary->continuesWith($stream->peek()->type)) {
            $symbol = $stream->nextIf(BisonLexeme::Identifier)?->asString();
            if ($symbol === null) {
                $stream->next();
                continue;
            }

            $symbols[] = $symbol;
        }

        return new BisonTypeDeclaration($typeTag, $symbols);
    }
}
