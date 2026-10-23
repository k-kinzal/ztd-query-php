<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Foreign;

use MySqlMemory\Command\Admin\Foreign\Fields;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testOptionsRetainNumericPortsAndUseTheHostSpecificField(): void
    {
        $session = (new Instance())->connect();
        $options = (new Fields())->options(['host' => 'abcédef', 'database' => '猫', 'port' => 3306], $session);

        self::assertSame(['host' => 'abc?def', 'database' => '猫', 'port' => 3306], $options);
        self::assertSame([['Warning', 1366, "Incorrect string value: '\\xC3\\xA9def' for column 'Host' at row 1"]], $session->diagnostics->conditions);
    }

    public function testTextBoundsCharactersBeforeAsciiConversionAndDropsCharPadding(): void
    {
        $session = (new Instance())->connect();
        $fields = new Fields();

        self::assertSame(str_repeat('x', 255), $fields->text(str_repeat('x', 255) . '猫', $session, 255, true));
        self::assertSame('a', $fields->text('a  ', $session));
        self::assertSame('a' . "\0" . 'b', $fields->text('a' . "\0" . 'b', $session));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testTextReadsLegacyInputBytesInTheClientCharacterSet(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $fields = new Fields();

        self::assertSame('c383c2a9', bin2hex($fields->text('é', $session)));
        self::assertSame(str_repeat('x', 63) . 'Ã', $fields->text(str_repeat('x', 63) . 'é', $session));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testWrapperUsesDecodedNamesAndTheReleaseStorageBoundary(): void
    {
        $fields = new Fields();

        self::assertSame('é', $fields->wrapper('é', (new Instance('5.7.44'))->connect()));
        self::assertSame('a', $fields->wrapper("a\0b", (new Instance('5.6.51'))->connect()));
        self::assertSame("a\0b", $fields->wrapper("a\0b", (new Instance())->connect()));
        self::assertSame('?', $fields->wrapper('😀', (new Instance())->connect()));
    }

    public function testCachedRetainsDecodedValuesBeforeTheFieldLimit(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        self::assertSame(['host' => 'Ã©', 'database' => str_repeat('x', 100), 'port' => 3306], (new Fields())->cached(['host' => 'é', 'database' => str_repeat('x', 100), 'port' => 3306], $session));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testChangedDistinguishesCachedInputFromLossyStorage(): void
    {
        $fields = new Fields();
        $session = (new Instance())->connect();

        self::assertSame([], $fields->changed(['host' => 'é', 'port' => 0, 'database' => ''], ['host' => 'é'], $session));
        self::assertSame(['host' => 'é'], $fields->changed(['host' => 'é'], ['host' => '?'], $session));
    }
}
