<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind;

#[CoversClass(ServerOptionKind::class)]
#[Small]
final class ServerOptionKindTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['USER', 'HOST', 'DATABASE', 'OWNER', 'PASSWORD', 'SOCKET', 'PORT'], array_column(ServerOptionKind::cases(), 'value'));
    }
}
