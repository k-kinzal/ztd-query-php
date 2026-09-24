<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\ResultColumn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ResultColumn::class)]
#[Small]
final class ResultColumnTest extends TestCase
{
    public function testRetainsTheNameAndType(): void
    {
        $column = new ResultColumn('id', TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'));
        self::assertSame('id', $column->name);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ResultColumn('', TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'));
    }
}
