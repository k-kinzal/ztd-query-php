<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Extension\AccessMethodKind;
use SqlSemantics\Model\Statement\Definition\Extension\CreateAccessMethodStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateAccessMethodStatement::class)]
#[Medium]
final class CreateAccessMethodStatementTest extends TestCase
{
    public function testBindsTheHandlerAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ACCESS METHOD bloom2 TYPE INDEX HANDLER app.blhandler');
        self::assertInstanceOf(CreateAccessMethodStatement::class, $statement);
        self::assertSame(['bloom2', AccessMethodKind::Index, ['app', 'blhandler']], [$statement->name, $statement->type, $statement->handler->parts]);
        self::assertSame('CREATE ACCESS METHOD "bloom2" TYPE INDEX HANDLER "app"."blhandler"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD m TYPE TABLE HANDLER h');
        self::assertInstanceOf(CreateAccessMethodStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE ACCESS METHOD "m" TYPE TABLE HANDLER "h"', $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD m TYPE TABLE HANDLER h');
        self::assertInstanceOf(CreateAccessMethodStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheMethod(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD m TYPE TABLE HANDLER h');
        self::assertInstanceOf(CreateAccessMethodStatement::class, $statement);
        self::assertSame('CREATE ACCESS METHOD "n" TYPE TABLE HANDLER "h"', $statement->withName('n')->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithTypeReplacesTheRelationKind(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD m TYPE TABLE HANDLER h');
        self::assertInstanceOf(CreateAccessMethodStatement::class, $statement);
        self::assertSame('CREATE ACCESS METHOD "m" TYPE INDEX HANDLER "h"', $statement->withType(AccessMethodKind::Index)->toString());
        self::assertSame(AccessMethodKind::Table, $statement->type);
    }

    public function testWithHandlerReplacesTheFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD m TYPE TABLE HANDLER h');
        self::assertInstanceOf(CreateAccessMethodStatement::class, $statement);
        self::assertSame('CREATE ACCESS METHOD "m" TYPE TABLE HANDLER "app"."h2"', $statement->withHandler(new QualifiedName(['app', 'h2']))->toString());
        self::assertSame(['h'], $statement->handler->parts);
    }
}
