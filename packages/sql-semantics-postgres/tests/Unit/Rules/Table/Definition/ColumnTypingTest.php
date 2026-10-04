<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping::class)]
#[Medium]
final class ColumnTypingTest extends TestCase
{
    public function testSerialAnswersTheIntegerType(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a bigserial, b public.serial)');
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n4);
        $n5 = $n4->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n5);
        $n6 = $n5->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n6);
        self::assertSame([
          0 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8,
          1 => null,
        ], [(new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping())->serial($n3->type), (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping())->serial($n6->type)]);
    }

    public function testDescriptorIsNullForATypeNameThatIsAnError(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int, c serial[], d int)');
        self::assertSame([
          0 => 'a integer Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testDescriptorNamesATypeTheContextCannotIdentify(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int, b mood, c text NOT NULL, d app.money[], e int)');
        self::assertSame([
          0 => 'a integer Nullable',
          1 => 'b mood Nullable',
          2 => 'c text NotNull',
          3 => 'd app.money[] Nullable',
          4 => 'e integer Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
        self::assertTrue($statement->declarations()[0]->complete);
    }

    public function testDescriptorIsTheCatalogTypeInACompleteContext(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (c text, d date[])', []);
        self::assertSame([
          0 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text,
          1 => 'date[]',
        ], [$statement->declarations()[0]->columns[0]->type, $statement->declarations()[0]->columns[1]->type->name()]);
    }

    public function testDescriptorOfAnotherStatementKeepsItsShape(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int)');
        self::assertSame([
          0 => 'a integer Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }
}
