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
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class QualifierScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement|Scalar>}>
     */
    public static function providerInputs(): iterable
    {
        $nodes = [];
        foreach ([null, new Name('db')] as $database) {
            foreach (['plain', 'values', 'default'] as $kind) {
                $column = new ColumnUse(new Name('c'), new QualifiedName(new Name('t'), $database));
                $value = match ($kind) {
                    'plain' => $column,
                    'values' => new InsertedColumn($column),
                    'default' => new DefaultOfColumn($column),
                };
                $nodes[] = $value;
                $nodes[] = new PurgeLogsBefore($value);
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<Statement|Scalar> $nodes
     */
    #[DataProvider('providerInputs')]
    public function testTablelessQualifiersCarryTheMissingRelationMeaning(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $session->text = 'unrelated invalid input';
                $observations = array_map(static function (Statement|Scalar $node) use ($native, $session): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());

                    return ['native' => serialize((new Native())->observe($native, $sql)), 'memory' => serialize((new Memory())->observe($session, $node))];
                }, $nodes);

                self::assertSame(array_column($observations, 'native'), array_column($observations, 'memory'), $release);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
