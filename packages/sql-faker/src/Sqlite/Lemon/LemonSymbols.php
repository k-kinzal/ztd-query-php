<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lemon;

/**
 * Remembers which names a Lemon grammar uses as tokens and which as rules.
 *
 * Lemon does not declare every symbol. It declares its tokens, and everything
 * else is a rule if some rule is written for it, so what a name means is only
 * known once the whole file has been read. A reader therefore says what it has
 * learned as it goes, and the question "is this a terminal" is answered here
 * rather than by whichever reader happens to be looking at the name.
 */
final class LemonSymbols
{
    /** @var array<string, true> Names the grammar declares or uses as tokens */
    private array $tokens = [];

    /** @var array<string, true> Names the grammar writes a rule for or expands to */
    private array $rules = [];

    /**
     * Records that a name is a token.
     *
     * @param string $name Token name as the grammar spells it
     */
    public function declareToken(string $name): void
    {
        $this->tokens[$name] = true;
    }

    /**
     * Records that a name is a rule.
     *
     * @param string $name Rule name as the grammar spells it
     */
    public function declareRule(string $name): void
    {
        $this->rules[$name] = true;
    }

    /**
     * Records every token named on one line of a directive.
     *
     * A directive lists its tokens separated by whitespace or, for a token
     * class, by an alternation bar, and the line may end in the period Lemon
     * uses to close a declaration. Anything on such a line that is not spelled
     * like a token is not one.
     *
     * @param string $line Everything the directive named
     * @param string $splitPattern Pattern separating one name from the next
     */
    public function declareTokensOn(string $line, string $splitPattern): void
    {
        $names = preg_split($splitPattern, trim($line));
        if ($names === false) {
            return;
        }
        foreach ($names as $name) {
            $name = trim($name, '.');
            if ($name !== '' && self::isTokenName($name)) {
                $this->declareToken($name);
            }
        }
    }

    /**
     * Reports whether a name stands for a token rather than a rule.
     *
     * What the grammar said outright wins. A name nothing said anything about
     * falls back to Lemon's spelling convention.
     *
     * @param string $name Symbol name to judge
     *
     * @return bool True when the name stands for a token
     */
    public function isTerminal(string $name): bool
    {
        if (isset($this->tokens[$name])) {
            return true;
        }
        if (isset($this->rules[$name])) {
            return false;
        }

        return self::isTokenName($name);
    }

    /**
     * Reports whether a name is spelled the way Lemon spells a token.
     *
     * @param string $name Symbol name to judge
     *
     * @return bool True when the name is written in capitals
     */
    public static function isTokenName(string $name): bool
    {
        return preg_match('/^[A-Z][A-Z0-9_]*$/', $name) === 1;
    }
}
