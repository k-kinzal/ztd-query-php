<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\SqlStatement;

#[CoversClass(SqlStatement::class)]
#[Medium]
final class SqlStatementTest extends TestCase
{
    public function testToStringRendersFromTheStructureAndNotFromTheInput(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select   a  ,b from   t where a>1 /* c */');

        self::assertSame('SELECT a, b FROM t WHERE a > 1', $operation->toString());
    }

    public function testToStringOfANewRootBuiltFromExplicitStructure(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $context = $semantics->context([]);

        $operation = Semantics::fromStatement($context, new Select([new ResultColumn(new IntegerLiteral('7'))]));

        self::assertSame('SELECT 7', $operation->toString());
        self::assertSame($context->profile, $operation->profile());
        self::assertFalse(property_exists($operation, 'context'));
        self::assertFalse(property_exists($operation, 'statement'));
        self::assertInstanceOf(IntegerLiteral::class, $operation->field(0)->expression);
        self::assertSame('7', $operation->field(0)->expression->digits);
    }

    public function testToStringIsNotReachedForANodeUsedAtTwoPositions(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $literal = new IntegerLiteral('1');

        $this->expectExceptionMessage('A scalar occurrence is resolved once.');

        Semantics::fromStatement($semantics->context([]), new Select([new ResultColumn($literal), new ResultColumn($literal)]));
    }

    public function testProfileIsTheProfileOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $operation = $semantics->analyze('SELECT 1');

        self::assertSame($semantics->profile(), $operation->profile());
    }

    public function testDeclarationsAnswerTheTablesAStatementProvides(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER, b TEXT)');

        self::assertCount(1, $operation->declarations());
        self::assertSame('t', $operation->declarations()[0]->name->name->value);
        self::assertSame(['a', 'b'], array_map(static fn ($column): string => $column->name->value, $operation->declarations()[0]->columns));
        self::assertSame([], (new Semantics(Dialect::Sqlite))->analyze('SELECT 1')->declarations());
    }












    public function testToStringPreservesExplicitSourcesWithoutInheritingThemOnReconstruction(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = new Select([new ResultColumn(new IntegerLiteral('7'))]);
        $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($statement, 2, 8)]);
        $operation = Semantics::fromStatement($semantics->context([]), $statement, $sources);
        self::assertEquals(new \SqlSemantics\Statement\Source\Span(2, 8), $operation->source());
        self::assertSame('SELECT 7', $operation->toString());
        self::assertNull(Semantics::fromStatement($semantics->context([]), $statement)->source());
    }
    public function testOutputBelongsToTheConcreteStatement(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS n');
        self::assertInstanceOf(Select::class, $query);
        self::assertSame($query->queryInformation(), $query->output());
        self::assertSame($query->output(), $query->output());
    }

    public function testInitializeStatementInformationCompletesOneOccurrenceOnly(): void
    {
        $query = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $information = new \SqlSemantics\Statement\StatementSemantics((new Semantics(Dialect::Sqlite))->profile());
        self::assertFalse($query->hasStatementInformation());
        $query->initializeStatementInformation($information);
        self::assertTrue($query->hasStatementInformation());
        self::assertSame($information, $query->statementInformation());
        $this->expectExceptionMessage('A statement occurrence is resolved once.');
        $query->initializeStatementInformation(new \SqlSemantics\Statement\StatementSemantics($information->profile));
    }

    public function testStatementInformationRequiresCompletedConstruction(): void
    {
        $query = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $this->expectExceptionMessage('A statement must be resolved before use.');
        $query->statementInformation();
    }

    public function testHasStatementInformationDistinguishesFreshConstruction(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1');
        self::assertTrue($query->hasStatementInformation());
        self::assertFalse((new \SqlSemantics\Construction\Occurrences())->copy($query)->hasStatementInformation());
    }
    public function testDiagnosticsRemainOnTheConcreteStatement(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t', []);
        self::assertSame('Relation t does not exist.', $statement->diagnostics()[0]->message());
    }

    public function testWarningsRetainTheirDeclaredOrder(): void
    {
        $first = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryBitwise);
        $second = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8Alias);
        $statement = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $statement->initializeStatementInformation(new \SqlSemantics\Statement\StatementSemantics((new Semantics(Dialect::Sqlite))->profile(), warnings: [$first, $second]));
        self::assertSame([$first, $second], $statement->warnings());
    }

    public function testNoticesPreserveTheirOwningExpression(): void
    {
        $value = new IntegerLiteral('1');
        $warning = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryBitwise);
        $notice = new \SqlSemantics\Statement\Fact\Notice($warning, $value);
        $statement = new Select([new ResultColumn($value)]);
        $statement->initializeStatementInformation(new \SqlSemantics\Statement\StatementSemantics((new Semantics(Dialect::Sqlite))->profile(), warnings: [$notice]));
        self::assertSame($notice, $statement->notices()[0]);
        self::assertSame($value, $statement->notices()[0]->subject);
    }


    public function testShapeDistinguishesDefinitionAndQueryResults(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertNull($semantics->analyze('CREATE TABLE t (a)')->shape());
        $shape = $semantics->analyze('SELECT 1 AS n')->shape();
        self::assertNotNull($shape);
        self::assertCount(1, $shape->slots);
    }

    public function testFieldsRetainResultOrder(): void
    {
        $fields = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS first, 2 AS second')->fields();
        self::assertNotNull($fields);
        self::assertSame('second', $fields->at(1)->name?->value);
    }

    public function testFieldResolvesAnOutputNameToItsPosition(): void
    {
        self::assertSame(1, (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS b')->field('b')->position);
    }

    public function testLookupFieldKeepsAnAbsentNameDistinct(): void
    {
        self::assertInstanceOf(\SqlSemantics\Statement\Shape\AbsentField::class, (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a')->lookupField('b'));
    }

    public function testInputRelationRetainsTheJoinStructure(): void
    {
        self::assertInstanceOf(\SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain::class, (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t, u')->inputRelation());
    }

    public function testSingleNamedInputRetainsTheSchema(): void
    {
        self::assertSame('main', (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM main.t')->singleNamedInput()->name()->schema?->value);
    }

}
