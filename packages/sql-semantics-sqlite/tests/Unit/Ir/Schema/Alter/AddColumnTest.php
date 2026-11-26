<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

#[CoversClass(AddColumn::class)]
#[Small]
final class AddColumnTest extends TestCase
{
    public function testToStringUsesTheAppendPositionAndLiteralForm(): void
    {
        $heading = [new Identifier('id')];
        $column = new TableColumn(new Identifier('quoted"name'), DeclaredDatatypeName::Text, default: new RealLiteral('1', null, '2', uppercaseExponent: true));
        $value = new AddColumn(new Identifier('main'), new Identifier('t'), $heading, false, $column);

        self::assertSame(1, $value->slot);
        self::assertSame([], $value->bindings);
        self::assertSame('ALTER TABLE "main"."t" ADD COLUMN "quoted""name" TEXT DEFAULT 1E2', $value->toString());
    }

    public function testProfileIdIdentifiesThePinnedSqliteProfile(): void
    {
        $value = new AddColumn(new Identifier('main'), new Identifier('t'), [new Identifier('id')], false, new TableColumn(new Identifier('added')));

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $value->profileId());
    }
}
