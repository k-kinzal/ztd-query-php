<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;

#[CoversClass(SizeOptionKind::class)]
#[Small]
final class SizeOptionKindTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['INITIAL_SIZE', 'AUTOEXTEND_SIZE', 'MAX_SIZE', 'EXTENT_SIZE', 'UNDO_BUFFER_SIZE', 'REDO_BUFFER_SIZE', 'FILE_BLOCK_SIZE'], array_column(SizeOptionKind::cases(), 'value'));
    }
}
