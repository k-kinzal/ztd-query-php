<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption;

#[CoversClass(EncryptionOption::class)]
#[Medium]
final class EncryptionOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("ALTER TABLESPACE ts ENCRYPTION 'Y'", (new Semantics(Dialect::MySql))->analyze("alter tablespace ts encryption 'Y'")->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('ENCRYPTION', (new EncryptionOption(new Text('Y')))->keyword());
    }
}
