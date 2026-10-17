<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Call\WeightResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(WeightResults::class)]
#[Small]
final class WeightResultsTest extends TestCase
{
    public function testCharacterBytesNarrowsBeforeChoosingTheResultField(): void
    {
        $modern = (new Semantics(Dialect::MySql))->analyze("SELECT WEIGHT_STRING(_utf8mb4'abc' COLLATE utf8mb4_bin AS CHAR(1073741824)), WEIGHT_STRING(_utf8mb4'abc' COLLATE utf8mb4_bin AS CHAR(65536))");
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("SELECT WEIGHT_STRING(_utf8mb4'abc' COLLATE utf8mb4_bin AS CHAR(1073741824))");

        self::assertEquals(new Known(Domain::string(8, Collation::binary())), $modern->field(0)->type);
        self::assertEquals(new Known(Domain::string(196608, Collation::binary(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::MediumBlob)), $modern->field(1)->type);
        self::assertEquals(new Known(Domain::string(0, Collation::binary())), $legacy->field(0)->type);
    }

    public function testFactResolvesCollationCapacityWithoutShorteningItForChar(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT WEIGHT_STRING(_utf8mb4'ab' COLLATE utf8mb4_general_ci AS CHAR(1)), WEIGHT_STRING(_utf8mb4'ab' COLLATE utf8mb4_general_ci,1,1,1)");

        self::assertEquals(new Known(Domain::string(16, Collation::binary())), $operation->field(0)->type);
        self::assertEquals(new Known(Domain::string(8, Collation::binary())), $operation->field(1)->type);
    }

    public function testCapacityIncludesLongerRequestedWeightCounts(): void
    {
        self::assertSame(16, (new WeightResults())->capacity(Collation::known('utf8mb3_bin'), 6, 8));
    }

    public function testCapacityKeepsBinaryByteCounts(): void
    {
        self::assertSame(4, (new WeightResults())->capacity(Collation::binary(), 2, 4));
    }

    public function testLengthIncludesCollationLevelSeparators(): void
    {
        self::assertSame(388, (new WeightResults())->length(Domain::string(2, Collation::known('utf8mb4_0900_as_cs'))));
        self::assertSame(24, (new WeightResults())->length(Domain::string(2, Collation::known('utf8mb4_bin'))));
    }
}
