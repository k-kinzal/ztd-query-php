<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StructureRequestHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement|Scalar>}>
     */
    public static function providerHistories(): iterable
    {
        $status = array_map(static fn (string $name): ShowStatus => new ShowStatus(filter: new ShowLike(new Text($name))), ['Questions', 'Com_set_option', 'Com_select', 'Com_signal', 'Com_show_status', 'Com_show_warnings']);
        $failure = new Signal(new SqlState(new Text('45000')));
        $history = [...$status, new NumberLiteral('7'), $failure, ...$status, new NumberLiteral('7'), $failure, ...$status];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $history];
        }
    }

    /**
     * @param list<Statement|Scalar> $history The same status objects before and after successful and failed requests
     */
    #[DataProvider('providerHistories')]
    public function testDirectRequestsRetainExactHistoryIncludingDiagnosticReads(string $release, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET SESSION sql_mode = '', collation_connection = 'utf8mb4_bin', timestamp = 1700000000");
                $session = (new Instance($release))->connect();
                $session->text = 'unrelated invalid input';
                (new SessionSetup())->apply($session, '');
                $observations = array_map(static function (Statement|Scalar $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual)];
                }, $history);

                self::assertSame(array_column($observations, 0), array_column($observations, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
