<?php

declare(strict_types=1);

namespace Tests\Fixture\SqlFaker;

use Faker\Generator;
use Override;

/**
 * A Faker whose numeric choices are written by the test, and recorded.
 *
 * Generation reaches for a number whenever it has to choose — which spelling
 * of a literal to write, which witness of a terminal to replay, how long to
 * make an identifier. A test given a real Faker can only check that something
 * plausible came out, which says nothing about the range that was chosen from:
 * a range one short at either end still produces plausible output every time.
 *
 * This answers each choice in turn from a script, so a test can walk every
 * branch, and records the bounds it was asked for, so a test can say what the
 * range was meant to be.
 */
final class ScriptedNumbers extends Generator
{
    /**
     * @var list<array{int, int}> The bounds of each choice, in the order they were asked for
     */
    public array $numberBetweenCalls = [];

    /**
     * @var list<int> Answers still to be given
     */
    private array $answers = [];

    /**
     * Builds a Faker that answers each choice in turn with these numbers.
     *
     * Once the script runs out, every further choice is answered with the
     * bottom of the range it was asked for, so a test only has to write the
     * choices it is actually about.
     *
     * @param int ...$answers Answers to give, in order
     *
     * @return self The Faker
     */
    public static function answering(int ...$answers): self
    {
        $faker = new self();
        $faker->answers = array_values($answers);

        return $faker;
    }

    /**
     * Answers the next scripted number, and records the bounds asked for.
     *
     * Faker declares both bounds untyped, so an override has to keep taking
     * anything; a caller writing something other than a number is asking for
     * the whole range, which is what Faker itself would make of it.
     *
     * @param mixed $int1 Bottom of the range
     * @param mixed $int2 Top of the range
     *
     * @return int The next scripted answer, or the bottom of the range
     */
    #[Override]
    public function numberBetween($int1 = 0, $int2 = 2147483647): int
    {
        $minimum = is_int($int1) ? $int1 : 0;
        $maximum = is_int($int2) ? $int2 : PHP_INT_MAX;
        $this->numberBetweenCalls[] = [$minimum, $maximum];

        return array_shift($this->answers) ?? $minimum;
    }
}
