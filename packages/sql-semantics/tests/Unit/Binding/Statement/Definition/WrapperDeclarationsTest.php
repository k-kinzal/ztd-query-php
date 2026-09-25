<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignDataWrapperStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\WrapperDeclarations::class)]
#[Medium]
final class WrapperDeclarationsTest extends TestCase
{
    public function testBindCreationUsesDistinctInitialOperandDomains(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN DATA WRAPPER fdw HANDLER app.h VALIDATOR app.v OPTIONS (format 'csv')");
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        self::assertNotNull($statement->handler);
        self::assertNotNull($statement->validator);
        self::assertSame(['app', 'h'], $statement->handler->parts);
        self::assertSame(['app', 'v'], $statement->validator->parts);
        self::assertSame('format', $statement->options[0]->name);
        self::assertSame("'csv'", $statement->options[0]->value->text);
    }

    public function testBindCreationNormalizesExplicitAndImplicitAbsence(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $implicit = $binder->bind('CREATE FOREIGN DATA WRAPPER fdw');
        $explicit = $binder->bind('CREATE FOREIGN DATA WRAPPER fdw NO HANDLER NO VALIDATOR');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $implicit);
        self::assertNull($implicit->handler);
        self::assertNull($implicit->validator);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($implicit), (new \SqlSemantics\SimpleSerializer())->serialize($explicit));
    }

    public function testBindAlterationSeparatesAddsReplacementsAndRemovals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER FOREIGN DATA WRAPPER fdw OPTIONS (ADD x 'new', SET y 'value', DROP z)");
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertSame(FunctionChange::Keep, $statement->handler);
        self::assertSame(FunctionChange::Keep, $statement->validator);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[0]);
        self::assertInstanceOf(SetForeignOption::class, $statement->options[1]);
        self::assertInstanceOf(DropForeignOption::class, $statement->options[2]);
        self::assertSame('x', $statement->options[0]->option->name);
        self::assertSame('y', $statement->options[1]->option->name);
        self::assertSame('z', $statement->options[2]->name);
    }

    public function testBindCreationDiagnosesRepeatedInitialOptions(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('unique names');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN DATA WRAPPER fdw OPTIONS (x 'first', x 'second')");
    }

}
