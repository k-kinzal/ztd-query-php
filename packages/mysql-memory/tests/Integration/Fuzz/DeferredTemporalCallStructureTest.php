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
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeferredTemporalCallStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     * @throws JsonException When the constructor catalogue cannot be decoded
     * @throws ReflectionException When a public constructor cannot be inspected
     */
    public static function providerStructures(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $structures = [];
        for ($trial = 0; $trial < 12; $trial++) {
            $construction = new Construction(new Choices('temporal-call-source:' . $trial), $catalogue, $metadata);
            $number = $construction->object(M\Literal\NumberLiteral::class, 0);
            assert($number instanceof M\Literal\NumberLiteral);
            $column = new M\Name\ColumnUse(new Name('a'));
            $input = match ($trial % 3) {
                0 => new M\Call\Extract(M\Expression\IntervalUnit::Day, $column),
                1 => new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Average, [$column]),
                2 => new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [$column]),
            };
            if ($trial >= 8) {
                $input = new M\Call\Aggregate\Aggregate($trial % 2 === 0 ? M\Call\Aggregate\AggregateFunction::Minimum : M\Call\Aggregate\AggregateFunction::Maximum, [new M\Call\Extract(M\Expression\IntervalUnit::Day, $column)]);
            }
            $zone = new M\Routine\Program\Expression\DeferredTimeZone($input, new M\Literal\Text('+00:00'));
            $call = $trial % 2 === 0 ? new M\Routine\Program\Expression\Call\DeferredGetFormat(M\Call\Temporal\TemporalFormat::Date, $zone) : new M\Routine\Program\Expression\Call\DeferredExtract(M\Expression\IntervalUnit::Day, $zone);
            $write = new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection($call), new M\Query\SelectExpression($number)], new M\Relation\TableReference(new QualifiedName(new Name('times'))));
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$write]);
            $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures The same retained temporal calls across branch and source states
     */
    #[DataProvider('providerStructures')]
    public function testNestedTemporalCallsRetainTheirOperandsAcrossStateTransitions(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $histories = array_map(static function (M\Routine\CreateProcedure $create) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($create, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $create);
                    self::assertSame($expected, $actual);
                    self::assertArrayNotHasKey('error', $expected);
                    $call = new M\Dml\ProcedureCall($create->name);
                    $history = array_map(static function (string $setup) use ($native, $session, $call): array {
                        array_map(static function (string $sql) use ($native, $session): void {
                            $native->query($sql);
                            $session->query($sql);
                        }, explode('; ', $setup));
                        $expected = (new Native())->observe($native, (new Reference())->sql($call, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $call);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, ['DROP TABLE IF EXISTS times; SET @active=0', 'SET @active=1', 'CREATE TABLE times(b TIMESTAMP NULL); INSERT INTO times VALUES(NULL)', 'ALTER TABLE times CHANGE b a TIMESTAMP NULL', 'SET @active=0', 'SET @active=1', 'DROP TABLE times']);
                    self::assertSame([null, 1146, 1054, 3998, null, 3998, 1146], array_column($history, 2));
                    return $history;
                }, $structures);
                $observed = array_merge(...$histories);
                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
