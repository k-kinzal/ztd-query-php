<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignDataWrapperStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Foreign\WrapperOptions::class)]
#[Medium]
final class WrapperOptionsTest extends TestCase
{
    public function testSupportOmitsKeptFunctionsAndWritesExplicitRemovals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertSame('ALTER FOREIGN DATA WRAPPER "fdw" NO HANDLER', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame([], \SqlSemantics\Serialization\Definition\Foreign\WrapperOptions::support(FunctionChange::Keep, false));
    }

    public function testOptionQuotesIdentifiersAndProtectsLiteralContents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $value = Expression::literal("value'); DROP SCHEMA app; --", Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new ForeignOption('x"y', $value)]);
        self::assertSame('x"y', $changed->options[0]->name);
        self::assertSame($value->text, $changed->options[0]->value->text);
        self::assertStringContainsString('"x""y"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testChangeSerializesEveryOptionOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER FOREIGN DATA WRAPPER fdw OPTIONS (format 'csv', SET path 'input', DROP old)");
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertSame('ALTER FOREIGN DATA WRAPPER "fdw" OPTIONS(ADD "format" \'csv\', SET "path" \'input\', DROP "old")', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['CREATE FOREIGN DATA WRAPPER w HANDLER h NO VALIDATOR', CreateForeignDataWrapperStatement::class, 'CREATE FOREIGN DATA WRAPPER "w" HANDLER "h" NO VALIDATOR'])]
    #[TestWith(['CREATE FOREIGN DATA WRAPPER w NO HANDLER VALIDATOR v', CreateForeignDataWrapperStatement::class, 'CREATE FOREIGN DATA WRAPPER "w" NO HANDLER VALIDATOR "v"'])]
    #[TestWith(['CREATE FOREIGN DATA WRAPPER w', CreateForeignDataWrapperStatement::class, 'CREATE FOREIGN DATA WRAPPER "w" NO HANDLER NO VALIDATOR'])]
    public function testWriteSpellsHandlerAndValidatorChoices(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
