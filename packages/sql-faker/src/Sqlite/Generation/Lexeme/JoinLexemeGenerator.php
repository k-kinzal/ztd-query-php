<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\LexicalException;

/**
 * Realizes JOIN_KW registrations under the complete join-type condition from select.c/sqlite3JoinType.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/select.c
 */
final class JoinLexemeGenerator implements LexemeGenerator
{
    /**
     * @param list<string> $words JOIN_KW spellings from the exact release's keyword table
     */
    public function __construct(private readonly array $words, private readonly JoinModifiers $types = new JoinModifiers())
    {
    }

    /**
     * Keeps candidates whose chosen suffix still admits a complete valid modifier sequence.
     * @throws LexicalException When a declared join word has no source-based implementation
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $scope = $input->terminal()->ancestor('joinop');
        if ($scope === null || !in_array($input->terminal()->name, ['JOIN_KW', 'JOIN_MODIFIER'], true)) {
            return null;
        }
        if ($this->words === []) {
            throw new LexicalException('Missing SQLite JOIN_KW registration data.');
        }
        $rightMask = 0;
        foreach ($input->right->parts as $part) {
            if ($part->lexeme->kind === 'join-modifier' && $part->lexeme->origin->ancestor('joinop') === $scope) {
                $rightMask |= $this->types->mask($part->lexeme->text);
            }
        }
        $remaining = 0;
        foreach (array_slice($input->terminals->terminals, 0, $input->index) as $terminal) {
            if ($terminal->ancestor('joinop') === $scope && in_array($terminal->name, ['JOIN_KW', 'JOIN_MODIFIER'], true)) {
                ++$remaining;
            }
        }
        $candidates = [];
        foreach ($this->words as $word) {
            $mask = $rightMask | $this->types->mask($word);
            if ($this->types->canComplete($mask, $remaining, $this->hasCondition($input), $this->words)) {
                $candidates[] = new LexemeSequence([
                    new Lexeme($word, 'join-modifier', $input->terminal(), 'src/select.c:sqlite3JoinType'),
                ], 'sqlite.join:' . $word);
            }
        }
        return LexemeCandidates::of(...$candidates);
    }

    /**
     * Checks the ON/USING child of the same source-list occurrence, without inspecting nested queries.
     */
    public function hasCondition(LexemeInput $input): bool
    {
        $list = $input->terminal()->ancestor('seltablist');
        $condition = $list === null ? null : $input->terminals->child($list, 'on_using');
        return $condition !== null && $input->terminals->range($condition->id) !== null;
    }
}
