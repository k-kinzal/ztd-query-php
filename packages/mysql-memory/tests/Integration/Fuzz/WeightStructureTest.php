<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class WeightStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>}>
     */
    public static function providerStructures(): iterable
    {
        $tables = array_map(static fn (string $name): M\Relation\TableReference => new M\Relation\TableReference(new QualifiedName(new Name($name), new Name('d'))), ['t', 'vm', 'vt']);
        $subjects = [
            new M\Literal\TemporalLiteral(M\Literal\TemporalForm::Date, '2024-01-01'),
            new M\Literal\TemporalLiteral(M\Literal\TemporalForm::Time, '-00:00:00.000001'),
            new M\Literal\TemporalLiteral(M\Literal\TemporalForm::Time, '838:59:59'),
            new M\Literal\TemporalLiteral(M\Literal\TemporalForm::Timestamp, '2024-01-01 01:02:03.123456'),
        ];
        foreach (['d', 'dt', 'ts', 'tm', 'y', 'b'] as $name) {
            $subjects[] = new M\Name\ColumnUse(new Name($name));
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $values = $subjects;
            if ($release !== '5.6.51') {
                $values[] = new M\Call\FunctionCall(new Name('JSON_OBJECT'), [new M\Call\CallArgument(new M\Literal\StringLiteral(['a'])), new M\Call\CallArgument(new M\Literal\NumberLiteral('1'))]);
            }
            $queries = [];
            foreach ($values as $subject) {
                $weights = [new M\Call\Weight\WeightString($subject), new M\Call\Weight\WeightStringParameters($subject, new M\Literal\Numeral('16'), new M\Literal\Numeral('1'), new M\Literal\Numeral('0'))];
                foreach ([M\Call\Weight\WeightCast::Char, M\Call\Weight\WeightCast::Binary] as $cast) {
                    foreach (['1', '32'] as $length) {
                        $weights[] = new M\Call\Weight\WeightString($subject, $cast, new M\Literal\Numeral($length));
                    }
                }
                foreach ($weights as $weight) {
                    foreach ($tables as $table) {
                        $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($weight, new Name('w'))], $table));
                    }
                }
            }
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<M\QueryRoot> $queries Original scalar compositions, retained across all row states
     */
    #[DataProvider('providerStructures')]
    public function testTheSameWeightStructuresRetainTheirDomainsAcrossEmptyPopulatedAndTruncatedInputs(string $release, array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                [$expected, $actual, $errors] = (new \Fuzz\Structure\Audit\Table\WeightHistory())->observe($session, $native, $queries);
                self::assertSame($expected, $actual);
                self::assertSame([], $errors);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

}
