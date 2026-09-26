<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * The comments written before the symbols of one SQL value, by symbol position.
 *
 * A position counts the symbols of a value's SQL form from zero, fixed words and
 * fields alike, so a comment stays with the symbol it was written before when
 * other fields are replaced. Every comment keeps its delimiters, and a line
 * comment ends before its line break. When the lexer reads the body of an
 * executable comment as SQL, its opening and closing delimiters are kept as
 * separate comments around the values they enclose.
 *
 * @visibility public
 * @example Placing a comment before the second symbol of a value
 *     $comments = new \SqlSemantics\Statement\Comments([1 => ['-- audited']]);
 *     $comments->before(1) // => ['-- audited']
 * @example Adding a comment to an existing position
 *     (new \SqlSemantics\Statement\Comments())->with(0, '-- first', '-- second')->before(0) // => ['-- first', '-- second']
 */
final class Comments
{
    use Assertion;

    /**
     * Matches a block comment, an executable comment delimiter, or a line comment.
     */
    private const COMMENT = '~\A(?:/\*.*|\*/|--[^\r\n]*|#[^\r\n]*)\z~sD';

    /**
     * Supplies the comments by the position of the symbol each precedes.
     *
     * @param array<int, list<string>> $texts Comments in writing order, by symbol position
     */
    public function __construct(private readonly array $texts = [])
    {
        foreach ($texts as $position => $comments) {
            $this->assert($position >= 0 && $comments !== [], 'A comment position must name a symbol and hold at least one comment.');
            foreach ($comments as $comment) {
                $this->assertMatchesPattern($comment, self::COMMENT, 'A comment must be a block comment, a line comment without its line break, or an executable comment delimiter.');
            }
        }
    }

    /**
     * Answers the comments written before the symbol at a position, in order.
     *
     * @return list<string>
     */
    public function before(int $position): array
    {
        return $this->texts[$position] ?? [];
    }

    /**
     * Lists the symbol positions that have comments, in ascending order.
     *
     * @return list<int>
     */
    public function positions(): array
    {
        $positions = array_keys($this->texts);
        sort($positions);

        return $positions;
    }

    /**
     * Returns a copy with more comments written before the symbol at a position.
     */
    public function with(int $position, string ...$comments): self
    {
        $texts = $this->texts;
        $texts[$position] = [...$this->before($position), ...array_values($comments)];

        return new self($texts);
    }
}
