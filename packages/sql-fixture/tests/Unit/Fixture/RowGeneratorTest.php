<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\RowGenerator;
use SqlFixture\Schema\TableSchema;
use stdClass;

#[CoversNothing]
final class RowGeneratorTest extends TestCase
{
    public function testGenerateAnswersTheRowTheImplementationBuilt(): void
    {
        $generator = self::createStub(RowGenerator::class);
        $generator->method('generate')->willReturn(['id' => 1]);

        self::assertSame(['id' => 1], $generator->generate(new TableSchema('users', [], [])));
    }

    public function testGenerateAnswersTheObjectWhenAClassIsNamed(): void
    {
        $entity = new stdClass();
        $generator = self::createStub(RowGenerator::class);
        $generator->method('generate')->willReturn($entity);

        self::assertSame($entity, $generator->generate(new TableSchema('users', [], []), [], stdClass::class));
    }
}
