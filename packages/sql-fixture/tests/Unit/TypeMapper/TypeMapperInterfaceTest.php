<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

#[CoversNothing]
final class TypeMapperInterfaceTest extends TestCase
{
    public function testGenerateAnswersTheValueTheImplementationChose(): void
    {
        $mapper = self::createStub(TypeMapperInterface::class);
        $mapper->method('generate')->willReturn(42);

        self::assertSame(42, $mapper->generate(Factory::create(), new ColumnDefinition('id', 'int')));
    }
}
