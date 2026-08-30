<?php

declare(strict_types=1);

namespace Tests\Fake;

/**
 * A Faker provider that writes the same text every time it is asked.
 *
 * A test about where a value is cut from cannot say so by asking Faker for
 * the same text twice: whether the answer comes back the same depends on how
 * many draws it took to get there, which is a fact about Faker rather than
 * about what is being tested. This one answers what it was told to, so the
 * cut is all that is left to disagree about.
 */
final class FixedTextProvider
{
    /**
     * @param string $written What every text it is asked for is written as
     */
    public function __construct(private readonly string $written)
    {
    }

    /**
     * Answers the text it was told to, cut to the length asked for.
     *
     * @param int $maxNbChars How long the text may be
     *
     * @return string The text
     */
    public function text(int $maxNbChars = 200): string
    {
        return substr($this->written, 0, $maxNbChars);
    }

    /**
     * Answers the text it was told to, as long as the pattern asks for.
     *
     * @param string $string Pattern of question marks to fill in
     *
     * @return string The text
     */
    public function lexify(string $string = '????'): string
    {
        return substr($this->written, 0, strlen($string));
    }
}
