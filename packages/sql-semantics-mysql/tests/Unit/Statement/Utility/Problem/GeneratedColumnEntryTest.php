<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry;

#[CoversClass(GeneratedColumnEntry::class)]
#[Small]
final class GeneratedColumnEntryTest extends TestCase
{
    public function testMessageDescribesTheRefusedClientEntry(): void
    {
        $problem = new GeneratedColumnEntry(12);

        self::assertSame('PARSE_GCOL_EXPR is not a client statement.', $problem->message());
        self::assertSame(['offset' => 12], get_object_vars($problem));
    }
}
