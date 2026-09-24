<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AlterTypeOptionsStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTypeOptionsStatement::class)]
#[Medium]
final class AlterTypeOptionsStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER TYPE t SET (typmod_in = s.tin, analyze, storage = MAIN)');
        self::assertInstanceOf(AlterTypeOptionsStatement::class, $statement);
        self::assertSame(['s', 'tin'], $statement->options[0]->value instanceof QualifiedName ? $statement->options[0]->value->parts : null);
        self::assertNull($statement->options[1]->value);
        self::assertSame('main', $statement->options[2]->value);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame("ALTER TYPE \"t\" SET (TYPMOD_IN = \"s\".\"tin\", ANALYZE = NONE, STORAGE = 'main')", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAStorageChangeWithoutStrategy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t SET (send = NONE)');
        self::assertInstanceOf(AlterTypeOptionsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new DefinitionOption(BaseTypeAttribute::Storage, null)]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t SET (send = NONE)');
        self::assertInstanceOf(AlterTypeOptionsStatement::class, $statement);
        self::assertSame('ALTER TYPE "t" SET (SEND = NONE)', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t SET (send = NONE)');
        self::assertInstanceOf(AlterTypeOptionsStatement::class, $statement);
        self::assertSame('ALTER TYPE "s"."u" SET (SEND = NONE)', $statement->withType(new QualifiedName(['s', 'u']))->toString());
        self::assertSame(['t'], $statement->type->parts);
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t SET (send = NONE)');
        self::assertInstanceOf(AlterTypeOptionsStatement::class, $statement);
        self::assertSame("ALTER TYPE \"t\" SET (STORAGE = 'external')", $statement->withOptions([new DefinitionOption(BaseTypeAttribute::Storage, 'external')])->toString());
    }
}
