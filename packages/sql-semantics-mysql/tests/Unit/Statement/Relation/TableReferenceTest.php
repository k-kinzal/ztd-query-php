<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TableReference::class)]
#[Medium]
final class TableReferenceTest extends TestCase
{
    public function testNameAnswersTheTableNameWithItsDatabase(): void
    {
        $input = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM shop.t AS x')->singleNamedInput();

        self::assertInstanceOf(TableReference::class, $input);
        self::assertSame('shop', $input->name()->schema?->value);
        self::assertSame('t', $input->name()->name->value);
        self::assertNull($input->name()->catalog);
        self::assertSame($input->name, $input->name());
    }

    public function testNameAnswersAnUnqualifiedNameWithoutADatabase(): void
    {
        $input = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t')->singleNamedInput();

        self::assertSame('t', $input->name()->name->value);
        self::assertNull($input->name()->schema);
    }

    public function testAliasAnswersTheCorrelationName(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('x', $semantics->analyze('SELECT a FROM t AS x')->singleNamedInput()->alias()?->value);
        self::assertSame('x', $semantics->analyze('SELECT a FROM t x')->singleNamedInput()->alias()?->value);
        self::assertNull($semantics->analyze('SELECT a FROM t')->singleNamedInput()->alias());
    }

    public function testDeriveRelationExposesOneSlotPerDeclaredColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT a FROM t', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        $fact = $operation->facts->relation($operation->statement->from);
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table, $fact->table->table);
        self::assertTrue($fact->shape->complete());
        self::assertCount(2, $fact->shape->slots);
        self::assertSame($table->columns[0], $fact->shape->slots[0]->column);
        self::assertSame($table->columns[1], $fact->shape->slots[1]->column);
        self::assertSame('b', $fact->shape->slots[1]->name?->value);
        self::assertInstanceOf(Known::class, $fact->shape->slots[1]->type);
        self::assertSame($table->columns[1]->type, $fact->shape->slots[1]->type->descriptor);
        self::assertSame(Nullability::Nullable, $fact->shape->slots[1]->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationLeavesTheShapeOpenForAnUndeclaredTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM shop.t AS x');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        $fact = $operation->facts->relation($operation->statement->from);
        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertFalse($fact->shape->complete());
        self::assertSame([], $fact->shape->slots);
        self::assertSame('the declaration of relation shop.t', $fact->shape->missing[0]->describe());
        self::assertSame($fact->table->missing, $fact->shape->missing[0]);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationReportsAMissingTableWithAnEmptyCompleteShape(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t', []);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        $fact = $operation->facts->relation($operation->statement->from);
        self::assertInstanceOf(MissingTable::class, $fact->table);
        self::assertTrue($fact->shape->complete());
        self::assertSame([], $fact->shape->slots);
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertSame('Relation t does not exist.', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveRelationResolvesAnUnqualifiedNameInTheCurrentDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('shop'));
        $table = new Table(new QualifiedName(new Name('t'), new Name('shop')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $unqualified = $semantics->analyze('SELECT a FROM t', [$table]);
        $qualified = $semantics->analyze('SELECT a FROM shop.t', [$table]);

        self::assertSame($table->columns[0], $unqualified->field('a')->column());
        self::assertSame($table->columns[0], $qualified->field('a')->column());
        self::assertSame('shop', $semantics->context()->searchPath[0]->value);
    }

    public function testDeriveRelationDoesNotFindATableOfAnotherDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT a FROM shop.t', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        self::assertInstanceOf(MissingTable::class, $operation->facts->relation($operation->statement->from)->table);
        self::assertSame('(current)', $semantics->context()->searchPath[0]->value);
    }

    public function testDeriveRelationReportsConflictingDeclarations(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $first = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $second = new Table(new QualifiedName(new Name('t')), $semantics->profile(), []);
        $operation = $semantics->analyze('SELECT 1 FROM t', [$first, $second]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        $fact = $operation->facts->relation($operation->statement->from);
        self::assertInstanceOf(ConflictingTables::class, $fact->table);
        self::assertSame([$first, $second], $fact->table->candidates);
        self::assertTrue($fact->shape->complete());
        self::assertSame([], $fact->shape->slots);
        self::assertSame('Relation t has conflicting declarations.', $operation->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheDatabaseTheNameAndTheCorrelationName(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT a FROM shop.t AS x', $semantics->analyze('select a from shop.t as x')->toString());
        self::assertSame('SELECT a FROM t x', $semantics->analyze('SELECT a FROM t x')->toString());
        self::assertSame('SELECT a FROM `order`', $semantics->analyze('SELECT a FROM `order`')->toString());
    }

    public function testACatalogQualifierIsRejected(): void
    {
        $this->expectExceptionMessage('A table is qualified by at most a database.');

        new TableReference(new QualifiedName(new Name('t'), new Name('db'), new Name('catalog')));
    }
}
