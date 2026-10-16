<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use MySqlMemory\Instance;
use MySqlMemory\Server\ConnectionEncoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(ConnectionEncoding::class)]
#[Small]
final class ConnectionEncodingTest extends TestCase
{
    public function testCollationFindsOnlyIdsAvailableInTheRelease(): void
    {
        $encoding = new ConnectionEncoding();
        self::assertSame(Collation::known('utf8mb4_general_ci'), $encoding->collation(new Instance('5.7.44'), 45));
        self::assertNull($encoding->collation(new Instance('5.7.44'), 255));
        self::assertNull($encoding->collation(new Instance(), 0));
        self::assertSame(Collation::known('utf8mb4_0900_ai_ci'), $encoding->collation(new Instance(), 255));
    }

    public function testApplyInstallsTheNegotiatedSetsWithoutAQuerySideEffect(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $encoding = new ConnectionEncoding();
        $encoding->apply($session, Collation::known('utf8mb4_general_ci'));
        $encoding->apply($session, null);
        self::assertSame(['utf8mb4', 'utf8mb4', 'utf8mb4', 'utf8mb4_general_ci'], array_map($session->variables->read(...), ['character_set_client', 'character_set_connection', 'character_set_results', 'collation_connection']));
        self::assertSame(Collation::known('utf8mb4_general_ci'), $session->variables->resultsCollation);
        $encoding->apply($session, Collation::known('utf8mb3_general_ci'));
        self::assertSame('utf8', $session->variables->read('character_set_client'));
    }
}
