<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use Override;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;

/**
 * tokenize.c getToken excludes INDEXED although parse.y idj allows it as a name.
 * Quoting that spelling keeps WINDOW/OVER recognized by their scanner lookahead.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/parse.y
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/tokenize.c
 */
final class WindowNameLexemeGenerator implements LexemeGenerator
{
    /**
     * Keeps spellings in the exact release's keyword registrations.
     */
    public function __construct(private readonly LexemeGenerator $keywords)
    {
    }

    /**
     * Quotes only an INDEXED name immediately following WINDOW or OVER.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $candidates = $this->keywords->generate($input);
        if ($candidates === null || $input->terminal()->name !== 'INDEXED'
            || !in_array($input->terminals->nameAt($input->index - 1), ['WINDOW', 'OVER'], true)) {
            return $candidates;
        }
        return new LexemeCandidates(static function () use ($candidates): iterable {
            foreach ($candidates->sequences() as $candidate) {
                yield new LexemeSequence(array_map(static fn (Lexeme $lexeme): Lexeme => new Lexeme(
                    '"' . str_replace('"', '""', $lexeme->text) . '"',
                    'identifier',
                    $lexeme->origin,
                    'src/tokenize.c:getToken:window-name',
                ), $candidate->lexemes), $candidate->id . ':window-name', $candidate->left, $candidate->boundaries, $candidate->sources(...));
            }
        });
    }
}
