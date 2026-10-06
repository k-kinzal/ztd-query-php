<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\DependentField;
use SqlSemantics\Statement\Shape\FieldLookup;
use SqlSemantics\Statement\Shape\UniqueField;

#[CoversClass(FieldLookup::class)]
#[Medium]
final class FieldLookupTest extends TestCase
{
    public function testTheFourOutcomesAreTheOnlyLookupResults(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $complete = $semantics->analyze('SELECT 1 AS a, 2 AS b, 3 AS b');

        self::assertInstanceOf(UniqueField::class, $complete->lookupField('a'));
        self::assertInstanceOf(AmbiguousFields::class, $complete->lookupField('b'));
        self::assertInstanceOf(AbsentField::class, $complete->lookupField('c'));
        self::assertInstanceOf(DependentField::class, $semantics->analyze('SELECT * FROM t')->lookupField('a'));
    }
}
