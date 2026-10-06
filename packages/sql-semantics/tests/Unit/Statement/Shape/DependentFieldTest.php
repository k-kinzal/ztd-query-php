<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Shape\DependentField;

#[CoversClass(DependentField::class)]
#[Medium]
final class DependentFieldTest extends TestCase
{
    public function testCandidatesAreTheKnownFieldsAndMissingNamesTheStarInputs(): void
    {
        $lookup = (new Semantics(Dialect::Sqlite))->analyze('SELECT *, 1 AS a FROM t')->lookupField('a');

        self::assertInstanceOf(DependentField::class, $lookup);
        self::assertSame('a', $lookup->name);
        self::assertCount(1, $lookup->candidates);
        self::assertSame(1, $lookup->candidates[0]->position);
        self::assertInstanceOf(UndeclaredRelation::class, $lookup->missing[0]);
        self::assertSame('the declaration of relation t', $lookup->missing[0]->describe());
    }

    public function testCandidatesMayBeEmptyWhileMissingIsNot(): void
    {
        $lookup = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t')->lookupField('a');

        self::assertInstanceOf(DependentField::class, $lookup);
        self::assertSame([], $lookup->candidates);
        self::assertCount(1, $lookup->missing);
    }

    public function testMissingIsNeverEmpty(): void
    {
        $this->expectExceptionMessage('A dependent lookup names its missing inputs.');

        new DependentField('a', [], []);
    }
}
