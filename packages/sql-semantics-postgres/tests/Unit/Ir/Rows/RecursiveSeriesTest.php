<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\RecursiveSeries;

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
}
