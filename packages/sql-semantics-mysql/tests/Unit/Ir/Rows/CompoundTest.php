<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\Compound;
use SqlSemantics\Platform\MySql\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(Compound::class)]
#[Small]
final class CompoundTest extends TestCase
{
    public function testProfileIdComesFromTheCheckedArms(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame($row->profileId(), $value->profileId());
    }

    public function testToStringKeepsSubtreeGrouping(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);
        $parent = new Compound($row, CompoundOperator::Except, true, $value);

        self::assertSame($row->toString() . ' EXCEPT ALL (' . $value->toString() . ')', $parent->toString());
    }

    public function testRefusesArmsFromDifferentLexicalModes(): void
    {
        $column = new ResultColumn(new IntegerLiteral('1'), new Identifier('a'));
        $ordinary = new Select([$column]);
        $ansi = new Select([$column], mode: ProjectionMode::AnsiQuotes);

        $this->expectExceptionMessage('Compound operands must have the same language profile.');

        new Compound($ordinary, CompoundOperator::Union, true, $ansi);
    }
}
