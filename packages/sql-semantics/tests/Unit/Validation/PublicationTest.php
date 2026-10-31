<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Script;
use SqlSemantics\Validation\Publication;

#[CoversClass(Publication::class)]
#[Medium]
final class PublicationTest extends TestCase
{
    public function testEstablishKeepsScalarReplacementsInsideThePublishedOperation(): void
    {
        $operation = (new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (SELECT 1 LIMIT 0)');
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery::class, $subquery);
        $replacement = $subquery->scalarInformation()->replacement;

        self::assertNotNull($replacement);
        self::assertTrue($replacement->hasBinding());
        self::assertSame($replacement, $subquery->query->queryInformation()->fields()?->at(0)->expression);
        self::assertSame('SELECT (SELECT 1 LIMIT 0)', $operation->toString());
    }

    public function testEstablishDerivesTheFactsAndRendersCorrespondingSql(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $use = new ColumnUse(new Name('a'));
        $statement = new Select([new ResultColumn($use, new Name('order'))], new TableInput(new QualifiedName(new Name('t'))));

        [$facts, $sql] = (new Publication())->establish($semantics->context([$table]), $statement);

        self::assertSame('SELECT a AS `order` FROM t', $sql);
        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($use)->resolution);
        self::assertSame($table->declarations()[0]->columns[0], $facts->scalar($use)->resolution->declaration());
        self::assertSame('order', $facts->output?->fields()?->at(0)->name?->value);
    }

    public function testEstablishRefusesANodeUsedAtTwoPositions(): void
    {
        $context = (new Semantics(Dialect::Sqlite))->context([]);
        $literal = new IntegerLiteral('1');

        $this->expectExceptionMessage('A scalar occurrence is resolved once.');

        (new Publication())->establish($context, new Select([new ResultColumn($literal), new ResultColumn($literal)]));
    }

    public function testEstablishAuditsSessionValuesBeforeDerivingFacts(): void
    {
        $session = new class () implements \SqlSemantics\Contract\Session {
        };
        $context = (new Semantics(Dialect::Sqlite))->context([], true, null, $session);

        $this->expectExceptionMessage('must be final');

        (new Publication())->establish($context, new Select([new ResultColumn(new IntegerLiteral('1'))]));
    }

    public function testRootIsTheOnlyStatementOrAScript(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $first = $semantics->analyze('SELECT 1');
        $second = $semantics->analyze('SELECT 2');

        $publication = new Publication();

        self::assertSame($first, $publication->root([$first]));
        $script = $publication->root([$first, $second]);
        self::assertInstanceOf(Script::class, $script);
        self::assertSame([$first, $second], $script->statements);
        $empty = $publication->root([]);
        self::assertInstanceOf(Script::class, $empty);
        self::assertSame([], $empty->statements);
    }

    public function testPublishReturnsAResolvedCopyWithItsOwnLocations(): void
    {
        $statement = new Select([new ResultColumn(new IntegerLiteral('7'))]);
        $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($statement, 2, 8)]);
        $result = (new Publication())->publish((new Semantics(Dialect::Sqlite))->context(), $statement, $sources);
        self::assertNotSame($statement, $result);
        self::assertFalse($statement->hasBinding());
        self::assertTrue($result->hasBinding());
        self::assertEquals(new \SqlSemantics\Statement\Source\Span(2, 8), $result->source());
    }

    public function testDeriveCompletesTheSuppliedOccurrencesWithoutSqlText(): void
    {
        $value = new IntegerLiteral('7');
        $statement = new Select([new ResultColumn($value, new Name('answer'))]);
        [$facts, $statements] = (new Publication())->derive((new Semantics(Dialect::Sqlite))->context(), $statement);
        self::assertSame($value, $facts->output?->fields()?->at(0)->expression);
        self::assertTrue($value->hasBinding());
        self::assertSame($value->scalarInformation(), $facts->scalar($value));
        self::assertSame($statement, $statements[0][0]);
    }

}
