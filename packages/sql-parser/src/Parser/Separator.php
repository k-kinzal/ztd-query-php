<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\Lexer\Tokenizer;

/**
 * Puts between two tokens whatever makes the text read back as both of them.
 *
 * What stands between two tokens decides what they read back as, and only
 * the lexer of the dialect knows how. Rather than restate its rules, this
 * asks it: a candidate is written out, read back, and kept when it reads as
 * the tokens it was written from.
 *
 * The trivia a token was read after is the first candidate, since a token
 * still standing where it was read wants what stood there. After it come
 * nothing at all, a space, and an empty comment. All of them are needed. A
 * number has to touch a dot to spell a name and stand off it to stay a
 * number; two minus signs have to touch to stay operators, while what
 * follows them has to touch the second one or be taken for a comment; and a
 * name is held off its bracket by a comment where a space would be ignored
 * and the name read as a call.
 *
 * Two things make this a search rather than a walk. A token is not always
 * settled when it is written, because what follows it can still decide what
 * it is: a quote turned against the letter before it makes that letter part
 * of the literal. And a choice that reads rightly now can leave nothing that
 * reads rightly later, as a keyword held against a dot stays a keyword until
 * a name follows the dot and turns it into one. So a choice that leads
 * nowhere is taken back and the next one tried, and only the last token,
 * which nothing follows, has to read back as itself for the text to stand.
 *
 * @visibility root
 */
final class Separator
{
    /**
     * What may stand between two tokens, least first.
     */
    private const CANDIDATES = ['', ' ', '/**/'];

    /**
     * How many candidates may be read back before a tree is given up on.
     */
    private const BUDGET = 20000;

    /**
     * @param Tokenizer $tokenizer Reads a candidate back
     * @param Spacing $spacing Says which candidate to try first
     */
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly Spacing $spacing = new Spacing(),
    ) {
    }

    /**
     * Names the terminals a text reads back as, with the text of each.
     *
     * @param string $sql The text
     *
     * @return list<string>|null The terminals, or null when the text holds something no token starts with
     */
    public function reading(string $sql): ?array
    {
        try {
            $read = $this->tokenizer->tokenize($sql);
        } catch (LexicalException) {
            return null;
        }
        $terminals = [];
        foreach ($read as $token) {
            if ($token->text !== '') {
                $terminals[] = $token->name . ' ' . $token->text;
            }
        }

        return $terminals;
    }

    /**
     * Names the terminals of tokens, with the text of each.
     *
     * @param list<Token> $tokens The tokens, none of them empty
     *
     * @return list<string> The terminals
     */
    public function terminals(array $tokens): array
    {
        return array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $tokens);
    }

    /**
     * Reports whether a text reads back as the tokens it was written from.
     *
     * Where the last token is still open to what follows it, everything
     * before it is asked for instead, which is as much as a text that is not
     * finished yet can be held to.
     *
     * @param string $sql The text
     * @param list<Token> $expected The tokens it was written from, none of them empty
     * @param bool $settled Whether the last token has to read back as itself
     *
     * @return bool True when the text reads back
     */
    public function reads(string $sql, array $expected, bool $settled = true): bool
    {
        $reading = $this->reading($sql);
        $terminals = $this->terminals($expected);
        if ($settled) {
            return $reading === $terminals;
        }

        return $reading !== null
            && count($reading) === count($terminals)
            && array_slice($reading, 0, -1) === array_slice($terminals, 0, -1);
    }

    /**
     * Writes tokens out, trying what may stand between them until it reads back.
     *
     * @param list<Token> $tokens The tokens, none of them empty
     *
     * @return string The SQL text
     *
     * @throws RenderException When no way of writing the tokens reads back as them
     */
    public function rebuild(array $tokens): string
    {
        $last = count($tokens) - 1;
        $texts = [''];
        $untried = [];
        $budget = self::BUDGET;
        $at = 0;
        while ($at <= $last) {
            $token = $tokens[$at];
            $previous = $at === 0 ? null : $tokens[$at - 1];
            $untried[$at] ??= $this->options($previous, $token, $at === $last);
            $written = null;
            while ($untried[$at] !== [] && $written === null && $budget-- > 0) {
                $option = array_shift($untried[$at]);
                $candidate = $texts[$at] . $option[0] . $token->text;
                $written = $this->reads($candidate, array_slice($tokens, 0, $at + 1), $option[1]) ? $candidate : null;
            }
            if ($budget <= 0) {
                throw RenderException::unreadable($token);
            }
            if ($written === null) {
                unset($untried[$at]);
                if ($previous === null) {
                    throw RenderException::unreadable($token);
                }
                --$at;
                continue;
            }
            $texts[++$at] = $written;
        }

        return $texts[$last + 1];
    }

    /**
     * Answers what may stand before a token, and how far each has to read back.
     *
     * Every candidate is first asked to settle the token it precedes, and
     * only then asked the weaker question of leaving everything before it
     * right, so a token is left open to what follows only where it has to be.
     * The last token is never left open, since nothing follows it.
     *
     * @param Token|null $previous The token before it, or null for the first
     * @param Token $token The token being written
     * @param bool $last Whether nothing follows it
     *
     * @return list<array{string, bool}> The candidates, with whether each has to settle the token
     */
    public function options(?Token $previous, Token $token, bool $last): array
    {
        if ($previous === null) {
            return [['', true]];
        }
        $candidates = $this->candidates($previous, $token);
        $options = array_map(static fn (string $candidate): array => [$candidate, true], $candidates);
        if ($last) {
            return $options;
        }

        return [...$options, ...array_map(static fn (string $candidate): array => [$candidate, false], $candidates)];
    }

    /**
     * Orders what may stand between two tokens by what they look to need.
     *
     * @param Token $previous The token on the left
     * @param Token $token The token on the right
     *
     * @return list<string> The candidates, likeliest first
     */
    public function candidates(Token $previous, Token $token): array
    {
        $preferred = $token->isDetached() ? ($this->spacing->separates($previous, $token) ? ' ' : '') : $token->leading;

        return [$preferred, ...array_values(array_filter(self::CANDIDATES, static fn (string $candidate): bool => $candidate !== $preferred))];
    }
}
