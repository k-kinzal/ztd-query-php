<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Settings::class)]
#[Small]
final class SettingsTest extends TestCase
{
    public function testDefaultsFollowTheRelease(): void
    {
        self::assertSame('utf8mb4_0900_ai_ci', Settings::defaults(GrammarRelease::MySql847)->connection->name);
        self::assertSame('latin1_swedish_ci', Settings::defaults(GrammarRelease::MySql5744)->connection->name);
        self::assertSame(4, Settings::defaults(GrammarRelease::MySql847)->divPrecisionIncrement);
    }

    public function testOfReadsTheSessionOfAContext(): void
    {
        $settings = new Settings(Collation::known('latin1_bin'), 6);
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame($settings, Settings::of($semantics->context([], true, null, $settings)));
        self::assertSame('utf8mb4_0900_ai_ci', Settings::of($semantics->context([]))->connection->name);
    }

    public function testSchemaFallsBackToTheServerThenTheConnection(): void
    {
        $settings = new Settings(Collation::known('latin1_bin'), 4, Collation::known('utf8mb4_bin'), ['App' => Collation::known('ascii_bin')]);

        self::assertSame('ascii_bin', $settings->schema('app')->name);
        self::assertSame('utf8mb4_bin', $settings->schema('other')->name);
        self::assertSame('latin1_bin', (new Settings(Collation::known('latin1_bin')))->schema('other')->name);
    }

    public function testClientIsTheCharacterSetStatementsAreReadIn(): void
    {
        self::assertSame('latin1', (new Settings(Collation::known('utf8mb4_0900_ai_ci'), 4, null, [], 1024, null, [], true, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1')))->client?->name);
        self::assertNull(Settings::defaults(GrammarRelease::MySql847)->client);
    }

    public function testUserVariablesAreKeyedByLowerCaseName(): void
    {
        self::assertSame(['a'], array_keys((new Settings(Collation::known('latin1_bin'), 4, null, [], 1024, ['A' => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer()]))->userVariables ?? []));
        self::assertNull((new Settings(Collation::known('latin1_bin')))->userVariables);
    }

}
