<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlParser\Parser\Node;

/**
 * Reads the comments out of the whitespace and comments a lexer skipped.
 *
 * Languages differ in how a block comment ends. Some nest block comments. Some
 * have executable comments: a block comment opened by `!` and a version number
 * whose body the lexer reads as SQL when the number does not exceed the parsed
 * release, so its opening and closing delimiters are found apart from each
 * other; an ordinary block comment in such a language steps over `/*` pairs
 * and nests only an executable comment. The rest end at the first `*` `/`.
 * Whether the reader is inside an executable comment carries from one run of
 * trivia to the next until a tree has been read.
 *
 * @visibility SqlSemantics
 */
final class TriviaReader
{
    private bool $executable = false;

    /**
     * @param bool $nestedBlocks Whether a block comment inside a block comment must close before the outer one
     * @param int|null $executableVersion The highest version number of an executable comment the lexer reads as SQL, or null when the language has none
     */
    public function __construct(private readonly bool $nestedBlocks = false, private readonly ?int $executableVersion = null)
    {
    }

    /**
     * Collects every comment of a parse tree, by the token it was written before.
     *
     * @throws LogicException When the tree's trivia holds text that is not a comment
     */
    public function read(Node $root): SourceComments
    {
        $this->executable = false;
        $tokens = $root->tokens();
        $last = count($tokens);
        while ($last > 0 && $tokens[$last - 1]->text === '') {
            $last--;
        }
        $leading = [];
        $before = [];
        $trailing = [];
        foreach ($tokens as $index => $token) {
            $comments = $this->comments($token->leading);
            if ($comments === []) {
                continue;
            }
            if ($index === 0) {
                $leading = $comments;
            } elseif ($index >= $last) {
                array_push($trailing, ...$comments);
            } else {
                $before[spl_object_id($token)] = $comments;
            }
        }
        array_push($trailing, ...$this->comments($root->trailing));

        return new SourceComments($leading, $before, $trailing);
    }

    /**
     * Splits one run of trivia into its comments, in order, without their surrounding whitespace.
     *
     * @return list<string>
     *
     * @throws LogicException When the trivia holds text that is not a comment
     */
    public function comments(string $trivia): array
    {
        $comments = [];
        $length = strlen($trivia);
        $offset = 0;
        while ($offset < $length) {
            if (ctype_space($trivia[$offset])) {
                $offset++;
                continue;
            }
            $end = $this->commentEnd($trivia, $offset);
            $comments[] = rtrim(substr($trivia, $offset, $end - $offset));
            $offset = $end;
        }

        return $comments;
    }

    /**
     * Finds where the comment at an offset ends, entering or leaving an executable comment on the way.
     *
     * @throws LogicException When the offset does not start a comment
     */
    public function commentEnd(string $trivia, int $offset): int
    {
        $pair = substr($trivia, $offset, 2);
        if ($pair === '--' || $pair[0] === '#') {
            $end = strpos($trivia, "\n", $offset);

            return $end === false ? strlen($trivia) : $end;
        }
        if ($pair === '*/' && $this->executable) {
            $this->executable = false;

            return $offset + 2;
        }
        if ($pair !== '/*') {
            throw new LogicException('Trivia holds text that is not a comment at offset ' . $offset);
        }
        if ($this->executableVersion !== null && !$this->executable && ($trivia[$offset + 2] ?? '') === '!') {
            $digits = preg_match('/\A[0-9]{5,6}/', substr($trivia, $offset + 3), $match) === 1 ? $match[0] : '';
            if ($digits === '' || (int) $digits <= $this->executableVersion) {
                $this->executable = true;

                return $offset + 3 + strlen($digits);
            }
        }

        return $this->blockEnd($trivia, $offset);
    }

    /**
     * Finds the offset just past the block comment that starts at an offset, or the end of the trivia when it never closes.
     */
    public function blockEnd(string $trivia, int $start): int
    {
        $length = strlen($trivia);
        if ($this->executableVersion === null && !$this->nestedBlocks) {
            $end = strpos($trivia, '*/', $start + 2);

            return $end === false ? $length : $end + 2;
        }
        $depth = 1;
        $offset = $start + 2;
        while ($offset < $length) {
            $pair = substr($trivia, $offset, 2);
            if ($pair === '*/') {
                if (--$depth === 0) {
                    return $offset + 2;
                }
                $offset += $this->nestedBlocks ? 2 : 1;
            } elseif ($pair === '/*') {
                if ($this->nestedBlocks || (!$this->executable && $depth === 1 && $trivia[$start + 2] !== '!' && ($trivia[$offset + 2] ?? '') === '!')) {
                    $depth++;
                }
                $offset += 2;
            } else {
                $offset++;
            }
        }

        return $length;
    }
}
