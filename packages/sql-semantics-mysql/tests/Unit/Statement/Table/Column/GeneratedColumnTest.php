<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;

#[CoversClass(GeneratedColumn::class)]
#[Medium]
final class GeneratedColumnTest extends TestCase
{
    /**
     * @return iterable<string, array{Type\DeclaredName, list<Column\ColumnAttribute>}>
     */
    public static function providerContradictorySpecifications(): iterable
    {
        $type = new Integral(Type\Kind\IntegralKind::Int);
        yield 'literal default' => [$type, [new Column\DefaultLiteral(new NumberLiteral('0'))]];
        yield 'expression default' => [$type, [new Column\DefaultExpression(new NumberLiteral('0'))]];
        yield 'automatic update' => [$type, [new Column\OnUpdate(new ClockCall(Clock::Now))]];
        foreach ([Column\Kind\ColumnKeyword::AutoIncrement, Column\Kind\ColumnKeyword::SerialDefaultValue] as $keyword) {
            yield $keyword->value => [$type, [new Column\KeywordAttribute($keyword)]];
        }
        yield 'format' => [$type, [new Column\FormatAttribute(Column\Kind\ColumnFormat::Fixed)]];
        yield 'storage' => [$type, [new Column\StorageAttribute(Column\Kind\StorageMedium::Memory)]];
        yield 'serial type' => [new Type\Elementary(Type\Kind\ElementaryKind::Serial), []];
    }

    /**
     * @param list<Column\ColumnAttribute> $attributes
     */
    #[DataProvider('providerContradictorySpecifications')]
    public function testRejectsAnIntrinsicGeneratedColumnContradiction(Type\DeclaredName $type, array $attributes): void
    {
        $this->expectExceptionMessage('A generated column cannot have a DEFAULT');

        new GeneratedColumn($type, new NumberLiteral('1'), attributes: $attributes);
    }

    public function testRejectsASessionFunctionBeforeOperationConstruction(): void
    {
        $this->expectExceptionMessage('A generation expression excludes subqueries, session variables, parameter markers and forbidden functions.');
        new GeneratedColumn(new Integral(Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []));
    }

    public function testRejectsFullTextSearchBeforeOperationConstruction(): void
    {
        $this->expectExceptionMessage('A generation expression excludes subqueries, session variables, parameter markers and forbidden functions.');
        new GeneratedColumn(
            new Integral(Type\Kind\IntegralKind::Int),
            new \SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch(
                [new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a'))],
                new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['x']),
            ),
        );
    }

    public function testDeriveSpecificationResolvesItsLeadingCollation(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t MODIFY a INT COLLATE unknown AS (1)');
        $problems = array_values(array_filter($operation->diagnostics(), static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation));

        self::assertCount(1, $problems);
        self::assertSame("Unknown collation: 'unknown'", $problems[0]->message());
    }

    public function testDataTypeAnswersTheType(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b BIGINT AS (a) VIRTUAL)');
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        $specification = $element1->specification;

        self::assertInstanceOf(GeneratedColumn::class, $specification);
        self::assertInstanceOf(Integral::class, $specification->dataType());
        self::assertSame(GeneratedStorage::Virtual, $specification->storage);
    }

    public function testColumnAttributesAnswersTheAttributes(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT AS (a) NOT NULL COMMENT \'x\')');
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);

        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        self::assertCount(2, $element1->specification->columnAttributes());
    }

    public function testDeriveSpecificationReportsAMissingColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT AS (c))', []);

        self::assertSame('Column c does not exist.', $create->diagnostics()[0]->message());
    }

    public function testRenderLeavesOutGeneratedAlways(): void
    {
        self::assertSame('CREATE TABLE t (a TEXT, b TEXT COLLATE utf8mb4_bin AS (a) STORED UNIQUE)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT, b TEXT COLLATE utf8mb4_bin GENERATED ALWAYS AS (a) STORED UNIQUE KEY)')->toString());
    }

    public function testRenderExcludesDefaultCollationInAGenerationDeclaration(): void
    {
        $this->expectExceptionMessage('A generated column names an explicit collation.');
        new GeneratedColumn(
            new Integral(Type\Kind\IntegralKind::Int),
            new NumberLiteral('1'),
            new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null),
        );
    }
}
