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
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LoadVersionStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<LoadTable>, list<int>}>
     */
    public static function providerStructures(): iterable
    {
        $target = new WriteTarget(new QualifiedName(new Name('t')));
        $file = new LoadInput(false, LoadSource::Infile, new Text('text'));
        $url = new LoadInput(false, LoadSource::Url, new Text('text'));
        $bulk = new BulkOptions(bulk: true);
        $statements = [
            new LoadTable(LoadFormat::Data, null, $url, null, $target),
            new LoadTable(LoadFormat::Data, null, new LoadInput(false, LoadSource::Infile, new Text('text'), new Numeral('1')), null, $target),
            new LoadTable(LoadFormat::Data, null, $file, null, $target, bulk: $bulk),
            new LoadTable(LoadFormat::Xml, null, $file, null, $target, bulk: $bulk),
            new LoadTable(LoadFormat::Data, null, new LoadInput(true, LoadSource::Infile, new Text('text')), null, $target, bulk: $bulk),
            new LoadTable(LoadFormat::Data, null, $file, DuplicateHandling::Ignore, $target, bulk: $bulk),
            new LoadTable(LoadFormat::Data, null, $url, null, $target, fields: [new FieldOption(FieldOptionKind::Enclosed, new Text('ab'))]),
            new LoadTable(LoadFormat::Data, null, $url, null, $target, columns: [new ColumnUse(new Name('missing'))]),
            new LoadTable(LoadFormat::Data, null, $file, null, new WriteTarget(new QualifiedName(new Name('t'), new Name('absent'))), bulk: $bulk),
        ];
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements, version_compare($release, '8.2.0', '<') ? [1146, 1290, 1290, 1146] : [1221, 1221, 1221, 1221]];
        }
    }

    /**
     * @param list<LoadTable> $statements The same structures before table creation, after creation and after removal
     * @param list<int> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testSemanticLoadOptionsRetainTheirVersionedMeaning(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE load_domain');
                $native->select_db('load_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE load_domain; USE load_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (LoadTable $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE TABLE t (a INT)', 'ALTER TABLE t ADD PRIMARY KEY (a)', 'DROP TABLE t']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): ?int => $state[0][2], $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
