<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\WrapperOptions::class)]
#[Medium]
final class WrapperOptionsTest extends TestCase
{
    public function testFunctionsPreservesNamedAndRemovedSupportFunctions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER VALIDATOR app.v');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertSame(FunctionChange::Remove, $statement->handler);
        self::assertInstanceOf(QualifiedName::class, $statement->validator);
        self::assertSame(['app', 'v'], $statement->validator->parts);
    }

    #[TestWith(['HANDLER h NO HANDLER'])]
    #[TestWith(['NO VALIDATOR VALIDATOR v'])]
    public function testFunctionsDiagnosesDuplicateSupportFunctionOptions(string $options): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('more than once');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw ' . $options);
    }

    public function testFunctionsDiagnosesTooManyQualificationParts(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw HANDLER a.b.c.d');
    }

    #[TestWith(['set'])]
    #[TestWith(['drop'])]
    #[TestWith(['add'])]
    public function testChangeDistinguishesKeywordOptionNamesFromOperationKeywords(string $name): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw OPTIONS (' . $name . " 'value')");
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[0]);
        self::assertSame($name, $statement->options[0]->option->name);
    }

    public function testChangeKeepsSequentialRemovalAndAdditionOfTheSameName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER FOREIGN DATA WRAPPER fdw OPTIONS (DROP x, ADD x 'new')");
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertInstanceOf(DropForeignOption::class, $statement->options[0]);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[1]);
        self::assertSame('x', $statement->options[0]->name);
        self::assertSame('x', $statement->options[1]->option->name);
    }

}
