<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Bindings;
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
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedScalarEvaluationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Scalar, int}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerScalars(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([30 => 3158, 71 => 3037] as $index => $error) {
            $choices = new Choices('row-roles-20261017:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            Check::invariant($node instanceof M\Call\Aggregate\JsonObjectAggregate || $node instanceof M\Expression\Conversion\Cast, 'The recorded whole-model roots retain their scalar identities.');
            $initial = substr((new Profiles())->select($node)->grammar->value, 6);
            foreach (array_unique([$initial, '8.4.7', '9.1.0']) as $release) {
                yield $release . ':' . $index => [$release, $node, $error];
            }
        }
    }

    #[DataProvider('providerScalars')]
    public function testTheSameScalarRetainsItsEvaluatedDataConditionAcrossRowStates(string $release, Scalar $node, int $code): void
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
                $setups = [new M\Table\CreateTable($name, [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)))]), new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($name)), [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('7')])]), new M\Alter\TruncateTable($name)];
                $history = array_map(static function (M\Statement $setup) use ($native, $session, $query): array {
                    $reference = new Reference();
                    self::assertSame((new Native())->observe($native, $reference->sql($setup, $session->semantics()->profile())), (new Memory())->observe($session, $setup));
                    $expected = (new Native())->observe($native, $reference->sql($query, $session->semantics()->profile(), '?'), count((new Bindings())->nulls($query)));
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $query));
                    return $expected;
                }, $setups);
                self::assertSame([null, $code, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame($history[0]['results'], $history[2]['results']);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
