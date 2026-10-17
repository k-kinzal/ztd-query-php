<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Function\Json\Outputs;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Outputs::class)]
#[Small]
final class OutputsTest extends TestCase
{
    public function testRoutinesNamesTheFunctions(): void
    {
        self::assertSame(['JSON_PRETTY', 'JSON_STORAGE_SIZE', 'JSON_STORAGE_FREE'], array_map(static fn ($routine): string => $routine->name, (new Outputs())->routines()));
    }

    public function testPrettyIndentsTheDocument(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_PRETTY('[1,{\"a\":[]}]')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([["[\n  1,\n  {\n    \"a\": []\n  }\n]"]], $result->rows);
    }

    public function testSizeAnswersTheBytesOfTheBinaryForm(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_STORAGE_SIZE('[1, \"abc\"]'), JSON_STORAGE_SIZE(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['15', null]], $result->rows);
    }

    public function testFreeIsZeroForADocumentStoredWhole(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_STORAGE_FREE('{}'), JSON_STORAGE_FREE(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', null]], $result->rows);
    }
}
