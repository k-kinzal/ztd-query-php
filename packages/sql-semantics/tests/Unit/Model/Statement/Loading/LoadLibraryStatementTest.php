<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\LoadLibraryStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoadLibraryStatement::class)]
#[Medium]
final class LoadLibraryStatementTest extends TestCase
{
    public function testWithOriginRetainsTheFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("LOAD 'a'");
        self::assertInstanceOf(LoadLibraryStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame("'a'", $copy->file->text);
        self::assertSame(StatementKind::Load, $copy->kind);
    }

    public function testWithFileLoadsAnotherLibrary(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("LOAD 'a'");
        $other = $binder->bind('LOAD $$b$$');
        self::assertInstanceOf(LoadLibraryStatement::class, $statement);
        self::assertInstanceOf(LoadLibraryStatement::class, $other);
        self::assertSame('LOAD $$b$$', $statement->withFile($other->file)->toString());
        self::assertSame("LOAD 'a'", $statement->toString());
    }

    public function testRejectsANumericFile(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("LOAD 'a'");
        $select = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $select);
        $number = $select->resultColumns()[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new LoadLibraryStatement($statement->origin, $number);
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("LOAD 'a'");
        self::assertInstanceOf(LoadLibraryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new LoadLibraryStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), $statement->file);
    }
}
