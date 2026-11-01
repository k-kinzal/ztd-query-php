<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\CollationApplicability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollationApplicability::class)]
#[Small]
final class CollationApplicabilityTest extends TestCase
{
    public function testRowsListsTheCharacterSetOfEachCollation(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query("SELECT * FROM information_schema.COLLATION_CHARACTER_SET_APPLICABILITY WHERE COLLATION_NAME = 'utf8mb4_0900_ai_ci'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['utf8mb4_0900_ai_ci', 'utf8mb4']], $result1->rows);
    }
}
