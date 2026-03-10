<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\LexicalValueGenerator;

#[CoversNothing]
final class LexicalValueGeneratorTest extends TestCase
{
    public function testLexicalValueGeneratorProducesExpectedPostgreSqlLiteralShapes(): void
    {
        $faker = Factory::create();
        $faker->seed(123);
        $generator = new LexicalValueGenerator($faker);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $generator->quotedIdentifier());
        self::assertMatchesRegularExpression("/^X'[0-9a-f]+'$/", $generator->hexLiteral());
    }
}
