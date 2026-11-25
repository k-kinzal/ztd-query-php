<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(DerivedSource::class)]
#[Small]
final class DerivedSourceTest extends TestCase
{
    public function testRetainsTheCheckedDefinition(): void
    {
        $definition = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('answer'))]);

        self::assertSame($definition, (new DerivedSource($definition))->definition);
    }

    public function testRejectsAnUnsupportedField(): void
    {
        $this->expectExceptionMessage('A derived join field requires a signed literal or a signed column, or a native VARCHAR column.');
        new DerivedSource(new Select([new ResultColumn(new NullLiteral(), new Identifier('answer'))]));
    }

    public function testColumnTypeDerivesEachExportedNativeKind(): void
    {
        $text = new Varchar(8);
        $name = new Identifier('label');
        $input = new TableSource(new Identifier('app'), new Identifier('source'), [$name], [$text]);
        $source = new DerivedSource(new Select([
            new ResultColumn(new IntegerLiteral('7'), new Identifier('number')),
            new ResultColumn(new TextColumnReference(0), $name),
        ], $input));

        self::assertSame(ColumnKind::RequiredSignedInt, $source->columnType(0));
        self::assertSame($text, $source->columnType(1));
        self::assertNull($source->columnType(2));
    }
}
