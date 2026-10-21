<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PurgeCutoffHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Replication\Log\PurgeLogsBefore}>
     */
    public static function providerStructures(): iterable
    {
        $operand = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Divide, new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('0'));
        $like = new M\Expression\Predicate\Like(new M\Expression\Grouped($operand), new M\Literal\StringLiteral(['x']), new M\Variable\UserVariable(new Name('escape')), true);
        $statement = new M\Replication\Log\PurgeLogsBefore(new M\Expression\TruthTest(new M\Expression\Grouped($like), M\Expression\Truth::Unknown));
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, $statement];
        }
    }

    #[DataProvider('providerStructures')]
    public function testOneCutoffObjectPreservesConditionsAcrossVariableChanges(string $release, M\Replication\Log\PurgeLogsBefore $statement): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release, ['log_bin' => 0]))->connect();
                $session->text = 'unrelated invalid input';
                $sql = (new Reference())->sql($statement, $session->semantics()->profile());
                $history = array_map(static function (string $escape) use ($session, $native, $statement, $sql): array {
                    $setup = new M\Utility\Set\SetVariables([new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('escape')), new M\Literal\StringLiteral([$escape]))]);
                    $before = (new Native())->observe($native, (new Reference())->sql($setup, $session->semantics()->profile()));
                    $actualBefore = (new Memory())->observe($session, $setup);
                    $expected = (new Native())->observe($native, $sql);
                    return [serialize($before), serialize($actualBefore), serialize($expected), serialize((new Memory())->observe($session, $statement)), $expected['error'][0] ?? null];
                }, ['!', 'xx', '!']);

                self::assertSame(array_column($history, 0), array_column($history, 1));
                self::assertSame(array_column($history, 2), array_column($history, 3));
                self::assertSame([null, 1210, null], array_column($history, 4));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
