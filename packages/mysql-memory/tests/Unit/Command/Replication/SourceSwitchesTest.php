<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\SourceSwitches;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(SourceSwitches::class)]
#[Small]
final class SourceSwitchesTest extends TestCase
{
    public function testCheckRequiresGtidModeBeforeFailover(): void
    {
        $option = new SourceOption(Terminology::Current, SourceOptionKind::ConnectionAutoFailover, new Numeral('1'));
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3990);
        (new SourceSwitches())->check([$option->kind->value => $option], $session, '');
    }

    public function testApplyPreservesPreviouslyConfiguredSwitches(): void
    {
        $option = new SourceOption(Terminology::Current, SourceOptionKind::GtidOnly, new Numeral('1'));
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON']))->connect();
        $state = $session->instance->registry->replication;
        $state->autoPosition = true;
        $state->requireRowFormat = true;
        $settings = new SourceSwitches();
        $settings->check([$option->kind->value => $option], $session, '');
        $settings->apply([$option->kind->value => $option], $session);

        self::assertSame([true, true, true, false], [$state->autoPosition, $state->requireRowFormat, $state->gtidOnly, $state->autoFailover]);
    }

    public function testValueUsesTheNumericOptionInterpretation(): void
    {
        $option = new SourceOption(Terminology::Current, SourceOptionKind::RequireRowFormat, new Numeral('0.9'));
        $settings = new SourceSwitches();

        self::assertFalse($settings->value([$option->kind->value => $option], $option->kind, true));
        self::assertTrue($settings->value([], $option->kind, true));
    }
}
