<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ServerRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ServerRule::class)]
#[Medium]
final class ServerRuleTest extends TestCase
{
    public function testStatementLowersTheServerStatements(): void
    {
        self::assertSame('SHOW GRANTS FOR u USING r, s', (new Inspection())->inspect('show grants for u using r, s')->toString());
        self::assertSame('SHOW ENGINES', (new Inspection())->inspect('show storage engines')->toString());
        self::assertSame('SHOW CHARSET', (new Inspection())->inspect('show character set')->toString());
    }
}
