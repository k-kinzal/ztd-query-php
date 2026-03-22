<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\Sqlite\LexicalValueGenerator;

#[CoversNothing]
final class LexicalValueGeneratorTest extends TestCase
{
    public function testLexicalValueGeneratorProducesExpectedSqliteLiteralShapes(): void
    {
        $faker = Factory::create();
        $faker->seed(123);
        $generator = new LexicalValueGenerator(new FakerRandomSource($faker));

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $generator->quotedIdentifier());
        self::assertMatchesRegularExpression("/^'[A-Za-z0-9_]+'$/", $generator->stringLiteral());
    }
}
