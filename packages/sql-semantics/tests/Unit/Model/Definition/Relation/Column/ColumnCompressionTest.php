<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Column\ColumnCompression::class)]
#[Medium]
final class ColumnCompressionTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['pglz', 'lz4', 'default'], array_column(Relation\Column\ColumnCompression::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET COMPRESSION default', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Column\SetColumnCompression('id', Relation\Column\ColumnCompression::Default), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET COMPRESSION default', $statement->toString());
    }
}
