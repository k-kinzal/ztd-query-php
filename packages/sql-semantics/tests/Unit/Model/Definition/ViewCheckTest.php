<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ViewCheck::class)]
#[Medium]
final class ViewCheckTest extends TestCase
{
    public function testRepresentsEveryCheckOptionScope(): void
    {
        self::assertSame(['', 'LOCAL', 'CASCADED'], array_column(ViewCheck::cases(), 'value'));
    }

    #[TestWith(['CREATE VIEW v AS SELECT 1 WITH LOCAL CHECK OPTION', ViewCheck::Local, 'CREATE VIEW "v" AS SELECT 1 WITH LOCAL CHECK OPTION'])]
    #[TestWith(['CREATE VIEW v AS SELECT 1 WITH CASCADED CHECK OPTION', ViewCheck::Cascaded, 'CREATE VIEW "v" AS SELECT 1 WITH CASCADED CHECK OPTION'])]
    #[TestWith(['CREATE VIEW v AS SELECT 1 WITH CHECK OPTION', ViewCheck::Cascaded, 'CREATE VIEW "v" AS SELECT 1 WITH CASCADED CHECK OPTION'])]
    #[TestWith(['CREATE VIEW v AS SELECT 1', ViewCheck::None, 'CREATE VIEW "v" AS SELECT 1'])]
    public function testBindsTheCheckOptionAndWritesItBack(string $sql, ViewCheck $check, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertSame($check, $statement->check);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
