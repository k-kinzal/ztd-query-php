<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\RecursiveSeries;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(RecursiveSeries::class)]
#[Small]
final class RecursiveSeriesTest extends TestCase
{
    public function testProfileIdIdentifiesTheDialectRelease(): void
    {
        $series = new RecursiveSeries(
            new IntegerLiteral('1'),
            new IntegerLiteral('2'),
            new IntegerLiteral('5'),
            IntegerOperator::Less,
            true,
            [new LocalResultColumn(0, new Identifier('value'))],
        );
        self::assertStringStartsWith($series->release->value . '/', $series->profileId());
    }

    public function testToStringEmitsTheDefinedStepAndRepeatedReads(): void
    {
        $series = new RecursiveSeries(
            new IntegerLiteral('5'),
            new IntegerLiteral('-2'),
            new IntegerLiteral('-3'),
            IntegerOperator::Greater,
            false,
            [new LocalResultColumn(0, new Identifier('value')), new LocalResultColumn(0, new Identifier('second'))],
        );
        $sql = $series->toString();

        self::assertStringStartsWith('WITH RECURSIVE ', $sql);
        self::assertStringContainsString(' UNION SELECT ', $sql);
        self::assertStringContainsString('second', $sql);
    }

    public function testRejectsAReleaseWithoutRecursiveWith(): void
    {
        $this->expectExceptionMessage('Recursive WITH requires MySQL 8.0 or later');
        new RecursiveSeries(
            new IntegerLiteral('0'),
            new IntegerLiteral('2'),
            new IntegerLiteral('5'),
            IntegerOperator::Less,
            true,
            [new LocalResultColumn(0, new Identifier('value'))],
            GrammarRelease::MySql5744,
        );
    }
}
