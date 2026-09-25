<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\PostgreSqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\PostgreSqlTable\Templates;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\TemplateProperty;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Templates::class)]
#[Medium]
final class TemplatesTest extends TestCase
{
    public function testReadCountsTheColumnsWrittenBeforeEachTemplate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE s(a INTEGER); CREATE TABLE r(b INTEGER)'));
        $statement = $binder->bind('CREATE TABLE t (LIKE s INCLUDING ALL EXCLUDING INDEXES, x INTEGER, y INTEGER, LIKE app.r)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame([0, 2], array_map(static fn ($template): int => $template->position, $statement->templates));
        self::assertSame(['app', 'r'], $statement->templates[1]->template->source->parts);
        self::assertSame(TemplateProperty::Indexes, $statement->templates[0]->template->selections[1]->property);
        self::assertFalse($statement->templates[0]->template->selections[1]->including);
        $expected = 'CREATE TABLE "public"."t"(LIKE "s" INCLUDING ALL EXCLUDING INDEXES, "x" integer, "y" integer, LIKE "app"."r")';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testReadFindsNoTemplateInAPlainDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (x INTEGER)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame([], $statement->templates);
    }
}
