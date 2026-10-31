<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;

#[CoversClass(DefinitionIssue::class)]
#[Small]
final class DefinitionIssueTest extends TestCase
{
    public function testMessageNamesTheIncompatibleDefinitionRole(): void
    {
        self::assertSame('Wrong number of subpartitions defined, mismatch with previous setting', DefinitionIssue::SubpartitionCount->message());
    }
}
