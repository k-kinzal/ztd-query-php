<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\TextGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
final class TextGeneratorTest extends TestCase
{
    public function testGenerateSupportsTextFamilies(): void
    {
        $faker = Factory::create();
        self::assertNotEmpty((new Subject())->generate($faker, new ColumnDefinition('body', 'TEXT')));
        self::assertNotEmpty((new Subject())->generate($faker, new ColumnDefinition('body', 'TINYTEXT')));
    }
}
