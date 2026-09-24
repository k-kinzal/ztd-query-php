<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\TypeOptions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TypeOptions::class)]
#[Medium]
final class TypeOptionsTest extends TestCase
{
    public function testBaseReadsAnalyseAndIgnoresUnknownAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o, analyse = a, "INPUT" = x, foo = 1)');
        self::assertInstanceOf(Statement\CreateBaseTypeStatement::class, $statement);
        self::assertSame([BaseTypeAttribute::Input, BaseTypeAttribute::Output, BaseTypeAttribute::Analyze], array_map(static fn ($option) => $option->attribute, $statement->options));
    }

    public function testRangeReadsEveryAttribute(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE r AS RANGE (subtype = int4, canonical = c)');
        self::assertInstanceOf(Statement\CreateRangeTypeStatement::class, $statement);
        self::assertCount(2, $statement->options);
    }

    public function testAlterKeepsTheLastArgument(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t SET (receive = r, send = NONE, receive)');
        self::assertInstanceOf(Statement\AlterTypeOptionsStatement::class, $statement);
        self::assertSame(BaseTypeAttribute::Receive, $statement->options[1]->attribute);
        self::assertNull($statement->options[1]->value);
    }

    #[TestWith(['CREATE TYPE t (input = i)', 'definition-requirement'])]
    #[TestWith(['CREATE TYPE t (input = i, output = o, input = j)', 'definition-attribute'])]
    #[TestWith(['CREATE TYPE t (input = i, output = o, analyze = a, analyse = b)', 'definition-attribute'])]
    #[TestWith(['CREATE TYPE t (input = i, output = o, default)', 'definition-argument'])]
    #[TestWith(['CREATE TYPE t (input = i, output = o, internallength = 0)', 'definition-argument'])]
    #[TestWith(['CREATE TYPE t (input = i, output = o, storage = compressed)', 'definition-argument'])]
    #[TestWith(["CREATE TYPE t (input = i, output = o, category = '')", 'definition-requirement'])]
    #[TestWith(['CREATE TYPE r AS RANGE (subtype = int, foo = 1)', 'definition-attribute'])]
    #[TestWith(['CREATE TYPE r AS RANGE (subtype = int, subtype = text)', 'definition-attribute'])]
    #[TestWith(['CREATE TYPE r AS RANGE (canonical = c)', 'definition-requirement'])]
    #[TestWith(['CREATE TYPE r AS RANGE (subtype = int, canonical)', 'definition-argument'])]
    #[TestWith(['ALTER TYPE t SET (input = i)', 'definition-attribute'])]
    #[TestWith(['ALTER TYPE t SET (analyse = a)', 'definition-attribute'])]
    #[TestWith(['ALTER TYPE t SET (storage)', 'definition-argument'])]
    public function testBaseRangeAndAlterDiagnoseImpossibleDefinitions(string $sql, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testAttributeMatchesOnlyLowercaseNames(): void
    {
        self::assertSame(BaseTypeAttribute::TypmodOut, TypeOptions::attribute('typmod_out'));
        self::assertNull(TypeOptions::attribute('Storage'));
    }
}
