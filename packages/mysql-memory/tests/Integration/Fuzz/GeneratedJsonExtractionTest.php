<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedJsonExtractionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Expression\Access\JsonExtraction, string}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerScalars(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('row-roles-20261017:66');
        $choices->one($roots);
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[66], 6);
        Check::invariant($node instanceof M\Expression\Access\JsonExtraction, 'The recorded whole-model root retains its JSON extraction identity.');
        $initial = substr((new Profiles())->select($node)->grammar->value, 6);
        foreach (array_unique([$initial, '8.4.7', '9.1.0']) as $release) {
            foreach ($release === '5.7.44' ? ['latin1', 'utf8mb4', 'binary', 'NULL'] : ['utf8mb4'] as $encoding) {
                yield $release . ':' . $encoding . ':generated' => [$release, $node, $encoding];
                yield $release . ':' . $encoding . ':quoted' => [$release, new M\Expression\Access\JsonExtraction($node->column, $node->path), $encoding];
            }
        }
    }

    #[DataProvider('providerScalars')]
    public function testTheSameExtractionRespondsToColumnTypesAndDocumentNullability(string $release, M\Expression\Access\JsonExtraction $node, string $encoding): void
    {
        $name = new QualifiedName(new Name('t'), new Name('d'));
        $query = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($node)], new M\Relation\TableReference($name)));
        self::assertContains($node, (new Walker())->find($query, $node::class));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $session = (new Instance($release, [], ['d']))->connect();
                $native->query('SET character_set_results=' . $encoding);
                $session->query('SET character_set_results=' . $encoding);
                $history = array_map(static function (string $setup) use ($native, $session, $query): array {
                    $reference = new Reference();
                    $setupNode = $session->analyze($setup)->statement;
                    self::assertInstanceOf(M\Statement::class, $setupNode);
                    self::assertSame((new Native())->observe($native, $setup), (new Memory())->observe($session, $setupNode));
                    $expected = (new Native())->observe($native, $reference->sql($query, $session->semantics()->profile()));
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $query));
                    return $expected;
                }, ['CREATE TABLE d.t(b INT)', 'INSERT INTO d.t VALUES(7)', 'DROP TABLE d.t', 'CREATE TABLE d.t(b JSON)', 'INSERT INTO d.t VALUES(NULL)', "UPDATE d.t SET b='{}'", 'UPDATE d.t SET b=NULL']);
                self::assertSame([null, 3146, 1146, null, null, 3143, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame($history[4]['results'], $history[6]['results']);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
