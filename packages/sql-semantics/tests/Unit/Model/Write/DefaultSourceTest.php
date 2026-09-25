<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Write\DefaultSource::class)]
#[Medium]
final class DefaultSourceTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testRepresentsTheDestinationDefaultWithoutAnExpression(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER DEFAULT 7)'));
        $insert = $binder->bind('INSERT INTO t VALUES (DEFAULT)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $insert);
        self::assertSame(\SqlSemantics\Model\Write\DefaultSource::Column, $insert->rows[0][0]);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($insert), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($insert))));
    }

    #[TestWith(['SELECT DEFAULT'])]
    #[TestWith(['VALUES (DEFAULT)'])]
    #[TestWith(['SELECT 1 + DEFAULT'])]
    #[TestWith(['SELECT ROW(DEFAULT)'])]
    #[TestWith(['SELECT 1 WHERE DEFAULT'])]
    public function testRejectsDefaultOutsideAWriteContext(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('DEFAULT requires');
        $binder->bind($sql);
    }
}
