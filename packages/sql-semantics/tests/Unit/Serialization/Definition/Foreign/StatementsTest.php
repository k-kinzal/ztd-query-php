<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Foreign\Statements;

#[CoversClass(Statements::class)]
#[Medium]
final class StatementsTest extends TestCase
{
    #[TestWith(['CREATE SERVER s FOREIGN DATA WRAPPER fdw'])]
    #[TestWith(['DROP SERVER s CASCADE'])]
    #[TestWith(['CREATE USER MAPPING FOR USER SERVER s'])]
    #[TestWith(['CREATE FOREIGN DATA WRAPPER fdw'])]
    #[TestWith(['IMPORT FOREIGN SCHEMA remote FROM SERVER s INTO local'])]
    public function testWriteRoutesEachForeignDefinitionFamily(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        $tree = Statements::write($statement);
        self::assertNotNull($tree);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), $tree->toString());
        self::assertSame($statement::class, $binder->bind($tree->toString())::class);
    }

    public function testWriteLeavesOtherDefinitionsForTheirOwnSerializer(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OWNED BY CURRENT_USER');
        self::assertNull(Statements::write($statement));
    }
}
