<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Facts::class)]
#[Medium]
final class FactsTest extends TestCase
{
    public function testIndexKeysPairsByNodeIdentity(): void
    {
        $first = new IntegerLiteral('1');
        $second = new IntegerLiteral('1');
        $fact = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $facts = new Facts([], [], [], [], null, []);

        $indexed = $facts->index([[$first, $fact], [$second, $fact]]);

        self::assertCount(2, $indexed);
        self::assertSame([$first, $fact], $indexed[spl_object_id($first)]);
        self::assertSame([$second, $fact], $indexed[spl_object_id($second)]);
    }

    public function testIndexRefusesANodeThatOccursTwice(): void
    {
        $node = new IntegerLiteral('1');
        $fact = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        new Facts([[$node, $fact], [$node, $fact]], [], [], [], null, []);
    }

    public function testScalarAnswersTheFactOfAnExpressionOfTheOperation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 + NULL');
        $expression = $operation->field(0)->expression;

        self::assertNotNull($expression);
        self::assertSame(Nullability::Nullable, $operation->facts->scalar($expression)->nullability);
    }

    public function testScalarRefusesAnExpressionOfAnotherOperation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT 1');
        $foreign = $semantics->analyze('SELECT 1')->field(0)->expression;

        self::assertNotNull($foreign);
        $this->expectExceptionMessage('The expression is not part of this operation.');

        $operation->facts->scalar($foreign);
    }

    public function testRelationAnswersTheFactOfAnOccurrenceOfTheOperation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t');

        $fact = $operation->facts->relation($operation->singleNamedInput());

        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertFalse($fact->shape->complete());
    }

    public function testRelationRefusesAnOccurrenceOfAnotherOperation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT a FROM t');
        $foreign = $semantics->analyze('SELECT a FROM t')->singleNamedInput();

        $this->expectExceptionMessage('The relation is not part of this operation.');

        $operation->facts->relation($foreign);
    }

    public function testQueryAnswersTheFactOfAQueryOfTheOperation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 AS x, 2 AS y)');
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(DerivedQuery::class, $statement->from);
        self::assertCount(2, $operation->facts->query($statement->from->query)->projection);
        self::assertCount(1, $operation->facts->query($statement)->projection);
    }

    public function testQueryRefusesAQueryOfAnotherOperation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT 1');
        $foreign = $semantics->analyze('SELECT 1')->statement;

        self::assertInstanceOf(Select::class, $foreign);
        $this->expectExceptionMessage('The query is not part of this operation.');

        $operation->facts->query($foreign);
    }

    public function testCoversTellsNodesOfTheOperationFromOthers(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT a FROM t');
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertTrue($operation->facts->covers($statement));
        self::assertTrue($operation->facts->covers($operation->singleNamedInput()));
        self::assertTrue($operation->facts->covers($statement->columns[0]->expression));
        self::assertFalse($operation->facts->covers($statement->columns[0]));
        self::assertFalse($operation->facts->covers($semantics->analyze('SELECT a FROM t')->singleNamedInput()));
    }

    public function testDeclarationsAndDiagnosticsAreTypedLists(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('CREATE TABLE t (a INTEGER, a TEXT)');

        self::assertCount(1, $operation->facts->declarations);
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
