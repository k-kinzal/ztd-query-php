<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

use Faker\Generator as FakerGenerator;

/**
 * Draws characters at random from an alphabet.
 *
 * Every shape of SQL text this package invents — an identifier, a hostname
 * label, the body of a string literal — is the same act with a different set
 * of characters allowed in it. Naming that act once keeps the shapes to their
 * own subject, which is which characters and how many.
 *
 * @visibility namespace
 */
final class RandomCharacters
{
    /**
     * @param FakerGenerator $faker Source of every choice a draw makes
     */
    public function __construct(private readonly FakerGenerator $faker)
    {
    }

    /**
     * Draws a string of a given length from an alphabet.
     *
     * @param string $alphabet Characters that may appear
     * @param int $length How many characters to draw
     *
     * @return string String of that length, empty when no characters were asked for
     */
    public function string(string $alphabet, int $length): string
    {
        $drawn = '';
        for ($index = 0; $index < $length; $index++) {
            $drawn .= $this->character($alphabet);
        }

        return $drawn;
    }

    /**
     * Draws one character from an alphabet.
     *
     * @param string $alphabet Characters that may appear
     *
     * @return string One of them
     */
    public function character(string $alphabet): string
    {
        return $alphabet[$this->faker->numberBetween(0, strlen($alphabet) - 1)];
    }
}
