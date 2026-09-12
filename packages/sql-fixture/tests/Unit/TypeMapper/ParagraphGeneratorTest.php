<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\TypeMapper\ParagraphGenerator as Subject;

#[CoversClass(Subject::class)]
final class ParagraphGeneratorTest extends TestCase
{
    public function testGenerateProducesJoinedParagraphs(): void
    {
        $faker = Factory::create();
        $faker->seed(4);
        $text = (new Subject())->generate($faker, 3);
        self::assertCount(3, explode("\n\n", $text));
        self::assertNotSame('', $text);
    }
}
