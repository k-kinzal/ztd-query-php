<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Execution\DoBlockStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DoBlockStatement::class)]
#[Medium]
final class DoBlockStatementTest extends TestCase
{
    public function testWithOriginRetainsTheBlock(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("DO 'x' LANGUAGE plperl");
        self::assertInstanceOf(DoBlockStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame('plperl', $copy->language);
        self::assertSame(StatementKind::Do, $copy->kind);
    }

    public function testWithCodeReplacesTheSource(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("DO 'x'");
        $other = $binder->bind('DO $$y$$');
        self::assertInstanceOf(DoBlockStatement::class, $statement);
        self::assertInstanceOf(DoBlockStatement::class, $other);
        self::assertSame('DO $$y$$', $statement->withCode($other->code)->toString());
        self::assertSame("DO 'x'", $statement->toString());
    }

    public function testWithLanguageSelectsOrClearsTheLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("DO 'x' LANGUAGE plperl");
        self::assertInstanceOf(DoBlockStatement::class, $statement);
        self::assertSame("DO 'x' LANGUAGE \"sql\"", $statement->withLanguage('sql')->toString());
        self::assertSame("DO 'x'", $statement->withLanguage(null)->toString());
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("DO 'x'");
        self::assertInstanceOf(DoBlockStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DoBlockStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::Sqlite), $statement->code);
    }

    public function testRejectsANumericCode(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("DO 'x'");
        $select = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $select);
        $number = $select->resultColumns()[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new DoBlockStatement($statement->origin, $number);
    }
}
