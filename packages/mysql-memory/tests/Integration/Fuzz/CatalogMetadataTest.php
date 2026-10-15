<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\CatalogRows;
use Fuzz\Structure\Audit\Fixture;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CatalogMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>}>
     */
    public static function providerCatalogs(): iterable
    {
        $absent = new ShowLike(new Text('never_this_routine'));
        $nodes = [new ShowWarnings(), new ShowErrors(), new ShowDatabases(), new ShowCharacterSet(), new ShowCollation(), new ShowVariables(filter: new ShowLike(new Text('version'))), new ShowFunctionStatus($absent), new ShowProcedureStatus($absent), new ShowGrants()];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<Statement> $nodes
     */
    #[DataProvider('providerCatalogs')]
    public function testDirectCatalogStructuresRetainReleaseMetadataAndRows(string $release, array $nodes): void
    {
        $definition = new DisposableMySql($release);
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $session = (new Fixture())->session($endpoint, $native, $release, $definition->supervised());
                $session->variables->session['character_set_results'] = 'utf8mb4';
                $session->text = 'unrelated invalid input';
                $observations = array_map(static function (Statement $node) use ($native, $session): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new CatalogRows())->comparable($node, (new Native())->observe($native, $sql));
                    $actual = (new CatalogRows())->comparable($node, (new Memory())->observe($session, $node));

                    return ['native' => serialize($expected), 'memory' => serialize($actual)];
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
