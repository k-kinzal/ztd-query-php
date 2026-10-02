<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;

#[CoversClass(C\Rendering\QuerySql::class)]
#[Small]
final class QuerySqlTest extends TestCase
{
    public function testWriteKeepsProjectionOrderAndDuplicates(): void
    {
        $field = new C\Query\FieldDefinition(new E\NullConstant(), new Name('same'));
        $input = new C\Query\SelectDefinition(new C\Query\ProjectionDefinition($field, $field), where: new E\NullConstant());
        self::assertSame('SELECT NULL AS same, NULL AS same WHERE NULL', (new C\Rendering\QuerySql())->write($input));
    }

    public function testLimitUsesCountAndOffsetRolesInBothSpellings(): void
    {
        $count = new E\SqliteInteger(new UnsignedInteger('2'));
        $offset = new E\SqliteInteger(new UnsignedInteger('3'));
        self::assertSame('LIMIT 2 OFFSET 3', (new C\Rendering\QuerySql())->limit(new C\Query\LimitDefinition($count, $offset)));
        self::assertSame('LIMIT 3, 2', (new C\Rendering\QuerySql())->limit(new C\Query\LimitDefinition($count, $offset, true)));
    }
}
