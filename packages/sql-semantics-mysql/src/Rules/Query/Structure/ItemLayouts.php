<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Validation\TokenCorrespondence;

/**
 * Checks a projected expression's observable spelling at construction.
 *
 * A layout spells the same tokens in the same grammatical roles under its
 * declared language profile. It cannot replace an operand, introduce syntax
 * through trivia, or hide several tokens in one slot. Parsing is local to
 * validation: no SQL string, parser tree or parser object survives in the item.
 * Naming and lexical correspondence are model invariants, independent of an
 * execution engine, schema or runtime state.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ItemLayouts
{
    /**
     * Refuses a layout that cannot represent the expression under its profile.
     *
     * @throws InvalidConstruction When the layout changes the expression or keeps redundant spelling
     */
    public function check(Scalar $expression, Layout $layout, LanguageProfile $profile): void
    {
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL select-item layout uses a MySQL language profile.');
        $naming = new ItemNaming($profile);
        $own = $naming->own($expression);
        Check::input($layout->tokens[0]->gap === '' || ($profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 && $own instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse && $own->qualifier === null), 'A select-item layout starts at its first token and keeps no leading trivia.');
        $platform = Platforms::of('mysql');
        $output = new Output($platform->codec($profile));
        $expression->render($output);
        Check::input(count($layout->tokens) === count($output->canonical()), 'A select-item layout has one token per expression token.');
        $text = $layout->tokens[0]->gap . $layout->text();
        Check::input($naming->keeps($expression) && $text !== $naming->canonical($expression), 'A select item keeps a layout only when MySQL names it after a text other than its canonical rendering.');
        $parser = $platform->parser($profile);
        try {
            foreach ($layout->tokens as $token) {
                $tokens = array_values(array_filter($parser->tokenize($token->text), static fn (Token $part): bool => $part->text !== ''));
                Check::input(count($tokens) === 1 && $tokens[0]->text === $token->text, 'Each select-item spelling slot contains exactly one complete token.');
            }
            $canonical = (new Lexical())->join($output->canonical());
            Check::input($this->pairs($parser->tokenize($canonical)) === $this->pairs($parser->tokenize($layout->text())), 'A select-item layout preserves contiguous negation pairs and their warnings.');
            $correspondence = new TokenCorrespondence();
            $productions = $platform->productions($profile);
            $keys = $platform->leafKeys($profile);
            $rendered = $correspondence->keys($parser->parse('SELECT ' . $canonical), $productions, $keys);
            $written = $correspondence->keys($parser->parse('SELECT ' . $text), $productions, $keys);
            Check::input($correspondence->difference($rendered, $written) === null, 'A select-item layout must preserve the expression under its spelling profile.');
        } catch (SourceException $error) {
            throw new InvalidConstruction('A select-item layout must preserve the expression under its spelling profile.', 0, $error);
        }
    }

    /**
     * Identifies the first token of each contiguous exclamation-mark pair.
     *
     * @param list<Token> $tokens Tokens of one expression
     * @return list<int> Pair positions in token order
     */
    public function pairs(array $tokens): array
    {
        $pairs = [];
        $pending = null;
        foreach ($tokens as $index => $token) {
            if ($token->name !== '!') {
                $pending = null;
            } elseif ($pending !== null && $tokens[$pending]->end() === $token->offset) {
                $pairs[] = $pending;
                $pending = null;
            } else {
                $pending = $index;
            }
        }

        return $pairs;
    }
}
