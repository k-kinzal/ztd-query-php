<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;
use SqlSemantics\Statement\Writer;

#[CoversClass(\SqlSemantics\Core\Ast\TypeInput::class)]
#[Medium]
final class TypeInputTest extends TestCase
{
    #[TestWith([MySql::MySql, 'DECIMAL', null, null, 10, 0])]
    #[TestWith([MySql::MySql, 'DECIMAL(0)', 0, null, 10, 0])]
    #[TestWith([MySql::MySql, 'DECIMAL(5)', 5, null, 5, 0])]
    #[TestWith([PostgreSql::PostgreSql, 'numeric(8)', 8, null, 8, 0])]
    #[TestWith([PostgreSql::PostgreSql, 'pg_catalog.numeric(2,-3)', 2, -3, 2, -3])]
    #[TestWith([PostgreSql::PostgreSql, 'numeric', null, null, null, null])]
    #[TestWith([Sqlite::Sqlite, 'DECIMAL(5)', 5, null, null, null])]
    public function testReadSeparatesDeclaredAndEffectiveNumericSize(Dialect $dialect, string $sql, ?int $precision, ?int $scale, ?int $effectivePrecision, ?int $effectiveScale): void
    {
        $declaration = (new Semantics($dialect))->type($sql);
        self::assertSame($precision, $declaration->type->precision);
        self::assertSame($scale, $declaration->type->scale);
        self::assertSame($effectivePrecision, $declaration->type->effectiveNumericSize?->precision);
        self::assertSame($effectiveScale, $declaration->type->effectiveNumericSize?->scale);
        self::assertNotNull($declaration->source);
        self::assertNotSame('', Writer::render($declaration->source));
        self::assertStringNotContainsString('SqlParser\\', serialize($declaration));
    }

    #[TestWith(['INT DEFAULT 1'])]
    #[TestWith(['INT, second TEXT'])]
    #[TestWith(['INT); DROP TABLE users; --'])]
    #[TestWith(['not a type'])]
    public function testReadRejectsAnythingOutsideOneType(string $sql): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Semantics(PostgreSql::PostgreSql))->type($sql);
    }

    public function testReadRetainsNamedModifiersAndArraySyntax(): void
    {
        $declaration = (new Semantics(PostgreSql::PostgreSql))->type('app.custom(7, 2)[][]');
        self::assertInstanceOf(\SqlSemantics\Statement\Declaration\TypeName::class, $declaration->type->name);
        self::assertSame(['app', 'custom'], $declaration->type->name->parts);
        self::assertSame(2, $declaration->type->arrayDimensions);
        self::assertNotNull($declaration->source);
        self::assertStringContainsString('7 , 2', Writer::render($declaration->source));
    }

}
