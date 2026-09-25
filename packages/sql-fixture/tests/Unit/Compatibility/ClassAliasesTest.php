<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\FixtureGenerator;
use SqlFixture\Provider\FixtureProvider;
use SqlFixture\Provider\PlatformFactory;

#[CoversNothing]
final class ClassAliasesTest extends TestCase
{
    public function testOriginalEntryPointsResolveToProviderComposition(): void
    {
        self::assertSame(FixtureProvider::class, (new \SqlFixture\FixtureProvider(Factory::create()))::class);
        self::assertSame(FixtureGenerator::class, (new \SqlFixture\FixtureGenerator(Factory::create()))::class);
        self::assertSame(PlatformFactory::class, (new \SqlFixture\Platform\PlatformFactory())::class);
    }

    public function testOriginalSchemaParserStillParsesTables(): void
    {
        self::assertSame('items', (new \SqlFixture\Schema\MySqlSchemaParser())->parse('CREATE TABLE items (id INT)')->tableName);
    }
}
