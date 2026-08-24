<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

/**
 * One lexeme read out of a Bison grammar file.
 *
 * The offset is kept alongside the value because the parser reports failures
 * against the grammar source, and a token that has been read is otherwise no
 * longer locatable in the file it came from.
 */
final class BisonToken
{
    /**
     * @param BisonLexeme $type Which lexeme was recognised
     * @param string|int $value The text it spelled, or its numeric value for a number
     * @param int $offset Where the lexeme starts in the grammar source
     */
    public function __construct(
        public readonly BisonLexeme $type,
        public readonly string|int $value,
        public readonly int $offset,
    ) {
    }
}
