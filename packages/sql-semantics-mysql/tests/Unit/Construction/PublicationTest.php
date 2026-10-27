<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Publication::class)]
#[Medium]
final class PublicationTest extends TestCase
{
    public function testPublishAttachesLocalMeaningWithoutChangingTheSuppliedStructure(): void
    {
        $number = new NumberLiteral('12');
        $statement = new QueryRoot(new Select([], [new SelectExpression($number, new Name('value'))]));
        $published = (new Publication())->publish((new Semantics(Dialect::MySql))->context(), $statement);
        self::assertInstanceOf(QueryRoot::class, $published);
        self::assertInstanceOf(Select::class, $published->query);
        self::assertInstanceOf(SelectExpression::class, $published->query->items[0]);
        self::assertFalse($number->hasBinding());
        self::assertFalse($statement->hasStatementInformation());
        self::assertTrue($published->query->items[0]->expression->hasBinding());
        self::assertSame($published->query->queryInformation(), $published->output());
        self::assertSame('value', $published->statementInformation()->field(0)->name?->value);
        self::assertSame('SELECT 12 AS `value`', $published->toString());
    }

    public function testRebindingKeepsInputNoticesAndRecomputesDerivedWarningsOnce(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $original = $semantics->analyze('SELECT /*+ FOO */ SQL_NO_CACHE 1 AS full');
        $bound = (new Publication())->publish($semantics->context(), $original);
        $rebound = (new Publication())->publish($semantics->context(), $bound);
        $messages = static fn (\SqlSemantics\Statement\AnalyzedStatement $statement): array => array_map(static fn ($warning): string => $warning->message(), $statement->warnings());
        self::assertCount(3, $original->warnings());
        self::assertSame($messages($original), $messages($bound));
        self::assertSame($messages($original), $messages($rebound));
        self::assertSame([false, true, true], array_map(static fn ($notice): bool => $notice->fromInput, $rebound->notices()));
        self::assertSame($rebound, $rebound->notices()[1]->subject);
        self::assertNotSame($original, $rebound->notices()[1]->subject);
        self::assertSame($original->notices()[1]->warning, $rebound->notices()[1]->warning);
        self::assertSame($original->notices()[0]->inputSource?->offset, $rebound->notices()[0]->inputSource?->offset);
    }

}
