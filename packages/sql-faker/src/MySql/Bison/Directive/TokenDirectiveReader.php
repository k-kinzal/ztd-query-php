<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use Override;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenDefinition;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%token`, which names the terminals the scanner may hand the parser.
 *
 * Each terminal is a name that may be followed by an explicit token code and by
 * a quoted alias, in that order and both optional, so the entries have to be
 * read one at a time rather than collected as a flat list of names.
 */
final class TokenDirectiveReader implements BisonDirectiveReader
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
     * Reports whether the directive is `%token`.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%token"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%token';
    }

    /**
     * Consumes the optional type tag and every terminal that follows it.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration The declaration, with no terminals when none followed
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): BisonDeclaration
    {
        unset($directive);

        $typeTag = $stream->nextIf(BisonLexeme::TypeTag)?->asString();

        /** @var list<BisonTokenDefinition> $declared */
        $declared = [];
        while ($this->boundary->continuesWith($stream->peek()->type)) {
            if ($stream->peek()->type !== BisonLexeme::Identifier) {
                $stream->next();
                continue;
            }

            $declared[] = $this->readTerminal($stream);
        }

        return new BisonTokenDeclaration($typeTag, $declared);
    }

    /**
     * Consumes one terminal together with the code and alias it may carry.
     *
     * @param BisonTokenStream $stream Stream positioned on the terminal's name
     *
     * @return BisonTokenDefinition The terminal, with null for whatever it omitted
     */
    public function readTerminal(BisonTokenStream $stream): BisonTokenDefinition
    {
        return new BisonTokenDefinition(
            $stream->nextString(),
            $stream->nextIf(BisonLexeme::Number)?->asInt(),
            $stream->nextIf(BisonLexeme::StringLiteral)?->asString(),
        );
    }
}
