<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\LexicalValueGenerator;

#[CoversNothing]
final class LexicalValueGeneratorTest extends TestCase
{
    public function testLexicalValueGeneratorProducesExpectedMySqlLiteralShapes(): void
    {
        $faker = Factory::create();
        $faker->seed(123);
        $generator = new LexicalValueGenerator($faker);

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]*`$/', $generator->quotedIdentifier());
        self::assertMatchesRegularExpression('/^0x[0-9a-f]+$/', $generator->hexLiteral());
    }
}
