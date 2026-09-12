<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

use Faker\Generator;
use UnexpectedValueException;

/**
 * Reads Faker paragraph text through its dynamically configured formatter boundary.
 *
 * @visibility root
 */
final class ParagraphGenerator
{
    /**
     * Keeps the formatter's paragraph separators and validates its string result.
     *
     * @throws UnexpectedValueException When a custom formatter violates the text contract
     */
    public function generate(Generator $faker, int $count): string
    {
        $text = $faker->paragraphs($count, true);
        if (!is_string($text)) {
            throw new UnexpectedValueException('The paragraphs formatter must return text.');
        }

        return $text;
    }
}
