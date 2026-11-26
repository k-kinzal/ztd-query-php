<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(EncryptionOption::class)]
#[Medium]
final class EncryptionOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("ALTER TABLESPACE ts ENCRYPTION 'Y'", (new Inspection())->inspect("alter tablespace ts encryption 'Y'")->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('ENCRYPTION', (new EncryptionOption(new Text('Y')))->keyword());
    }
}
