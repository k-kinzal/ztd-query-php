<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedSource;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(DerivedSource::class)]
#[Small]
final class DerivedSourceTest extends TestCase
{
    public function testRetainsTheCheckedDefinition(): void
    {
        $definition = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('answer'))]);

        self::assertSame($definition, (new DerivedSource($definition))->definition);
    }

    public function testColumnKindFollowsTheDefinitionOutputOrder(): void
    {
        $input = new TableSource(new Identifier('public'), new Identifier('messages'), [new Identifier('message')], false, [ColumnKind::Text]);
        $heading = [
            new ResultColumn(new IntegerLiteral('5'), new Identifier('same')),
            new ResultColumn(new TextColumnReference(0), new Identifier('same')),
        ];
        $source = new DerivedSource(new Select($heading, $input));

        self::assertSame(ColumnKind::Int4, $source->columnKind(0));
        self::assertSame(ColumnKind::Text, $source->columnKind(1));
        self::assertNull($source->columnKind(2));
    }

    public function testRejectsAnUnsupportedField(): void
    {
        $this->expectExceptionMessage('A derived join field requires a signed literal or a supported native column.');
        new DerivedSource(new Select([new ResultColumn(new NullLiteral(), new Identifier('answer'))]));
    }
}
