<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\StrictTypeViolation;

#[CoversClass(StrictTypeViolation::class)]
#[Small]
final class StrictTypeViolationTest extends TestCase
{
    public function testMessageNamesAnUnknownDatatype(): void
    {
        self::assertSame('Column a of a STRICT table has the unknown datatype VARCHAR(10).', (new StrictTypeViolation(new Name('a'), 'VARCHAR(10)'))->message());
    }

    public function testMessageNamesAMissingDatatype(): void
    {
        self::assertSame('Column a of a STRICT table has no datatype.', (new StrictTypeViolation(new Name('a'), ''))->message());
    }
}
