<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\EncodingSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(EncodingSettings::class)]
#[Small]
final class EncodingSettingsTest extends TestCase
{
    public function testNameResolvesIntegerIdsWithoutChangingWrittenAliases(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $settings = new EncodingSettings(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        self::assertSame('utf8mb3', $settings->name(33, true));
        self::assertSame('utf8mb3_general_ci', $settings->name(33, false));
        self::assertSame('utf8', $settings->name('utf8', true));
        self::assertSame('33', $settings->name('33', true));
    }

    public function testNoticesKeepsTheEvaluatedAliasBeforeTheAssignment(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $settings = new EncodingSettings(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $settings->notices('character_set_results', 'utf8', Domain::integer());
        self::assertSame([['Warning', 3719, "'utf8' is currently an alias for the character set UTF8MB3, but will be an alias for UTF8MB4 in a future release. Please consider using UTF8MB4 in order to be unambiguous."]], $session->diagnostics->conditions);
    }

    public function testClientRejectsFixedWidthEncoding(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $settings = new EncodingSettings(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $charset = Charset::named('ucs2');
        self::assertNotNull($charset);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $settings->client('character_set_client', $charset);
    }

    public function testCompatibleRejectsACollationFromAnotherCharacterSet(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $settings = new EncodingSettings(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1253);
        $settings->compatible('utf8', 'latin1_bin');
    }
}
