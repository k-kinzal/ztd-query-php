<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AbsentField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AmbiguousFields;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\DependentField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\FieldLookup;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\UniqueField;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(FieldLookup::class)]
#[Medium]
final class FieldLookupTest extends TestCase
{
    public function testTheFourOutcomesAreTheOnlyLookupResults(): void
    {
        $semantics = new Inspection();
        $complete = $semantics->inspect('SELECT 1 AS a, 2 AS b, 3 AS b');

        self::assertInstanceOf(UniqueField::class, $complete->lookupField('a'));
        self::assertInstanceOf(AmbiguousFields::class, $complete->lookupField('b'));
        self::assertInstanceOf(AbsentField::class, $complete->lookupField('c'));
        self::assertInstanceOf(DependentField::class, $semantics->inspect('SELECT * FROM t')->lookupField('a'));
    }
}
