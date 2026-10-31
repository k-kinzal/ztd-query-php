<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Replication;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Replication\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(Settings::class)]
#[Small]
final class SettingsTest extends TestCase
{
    public function testErrorFormatsRetainedOperandsWithoutReadingInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $settings = new Settings();
        self::assertSame(1525, $settings->error(new RefusedSetting(ReplicationError::LineFeed, text: new Text("a\nb")), $session)?->getCode());
        $until = new RefusedSetting(ReplicationError::UntilCondition);
        self::assertStringEndsWith('START REPLICA UNTIL', $settings->error($until, $session)?->getMessage() ?? '');
        self::assertStringEndsWith('START SLAVE UNTIL', $settings->error($until, (new Instance('5.7.44'))->connect())?->getMessage() ?? '');
        self::assertNull($settings->error(new RefusedSetting(ReplicationError::HeartbeatOutOfRange), $session));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
