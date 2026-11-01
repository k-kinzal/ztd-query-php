<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Construction\Occurrences::class)]
#[Medium]
final class OccurrencesTest extends TestCase
{
    public function testCopyPreservesOperandsAndRangesWithFreshBindings(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 12');
        self::assertInstanceOf(\SqlSemantics\Platform\Sqlite\Statement\Query\Select::class, $statement);
        $copy = (new \SqlSemantics\Construction\Occurrences())->copy($statement);
        self::assertNotSame($statement, $copy);
        self::assertFalse($copy->hasBinding());
        self::assertSame($statement->source(), $copy->source());
        self::assertNotSame($statement->columns[0], $copy->columns[0]);
        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference($statement, $copy));
    }

    public function testValuePreservesSharingWithinTheCopiedStructure(): void
    {
        $literal = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $copy = new \SqlSemantics\Construction\Occurrences();
        $items = $copy->value([$literal, $literal, 7]);
        self::assertIsArray($items);
        self::assertSame($items[0], $items[1]);
        self::assertNotSame($literal, $items[0]);
        self::assertSame(7, $items[2]);
    }

    public function testSourcesTransfersInputRangesAndNoticeSubjectsToCopiedOccurrences(): void
    {
        $literal = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $warning = self::createStub(\SqlSemantics\Statement\Fact\Warning::class);
        $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($literal, 7, 1)], [new \SqlSemantics\Statement\Source\SourceNotice($literal, $warning, 8)]);
        $copies = new \SqlSemantics\Construction\Occurrences($sources);
        $copy = $copies->copy($literal);
        $transferred = $copies->sources();
        self::assertSame($copy, $transferred->origins[0]->node);
        self::assertSame($copy, $transferred->notices[0]->subject);
        self::assertSame($warning, $transferred->notices[0]->warning);
        self::assertNotNull($copy->source());
        self::assertSame([7, 1], [$copy->source()->offset, $copy->source()->length]);
    }
}
