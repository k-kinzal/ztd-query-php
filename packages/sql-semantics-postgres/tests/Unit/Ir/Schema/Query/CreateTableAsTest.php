<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(CreateTableAs::class)]
#[Small]
final class CreateTableAsTest extends TestCase
{
    public function testProfileIdUsesTheDefiningQueryRelease(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('snapshot'));
        $column = new ResultColumn(new IntegerLiteral('5'), new Identifier('value'));
        $table = new CreateTableAs($name, new Select([$column], release: GrammarRelease::PostgreSql166), [$column->name]);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $table->profileId());
    }

    public function testToStringClosesTheUnloggedEmptyTablePolicy(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('snapshot'));
        $column = new ResultColumn(new IntegerLiteral('5'), new Identifier('original'));
        $table = new CreateTableAs($name, new Select([$column]), [new Identifier('output')], true, true, false);

        self::assertSame('CREATE UNLOGGED TABLE IF NOT EXISTS "app"."snapshot" ("output")'
            . ' USING heap AS SELECT CAST(5 AS pg_catalog.int4) AS "original" WITH NO DATA', $table->toString());
    }
}
