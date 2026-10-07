<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ColumnDefinition::class)]
#[Small]
final class ColumnDefinitionTest extends TestCase
{
    public function testNullableFollowsTheNullabilityOfTheDomain(): void
    {
        $nullable = new ColumnDefinition('a', Domain::integer(Field::Long, 11)->withNullable(true), Fill::none());
        $required = new ColumnDefinition('b', Domain::integer(Field::Long, 11), Fill::none());

        self::assertSame([true, false], [$nullable->nullable(), $required->nullable()]);
    }
}
