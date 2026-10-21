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
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class InsertGroupedValueTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int, bool}>}>
     */
    public static function providerSql(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $expressions = ['SUM(1)', 'ABS(SUM(missing))', 'GROUP_CONCAT(1)'];
            if (!str_starts_with($release, '5.')) {
                array_push($expressions, 'ROW_NUMBER() OVER ()', 'SUM(1) OVER ()', 'JSON_OBJECTAGG(1,2)');
            }
            $inputs = [['CREATE DATABASE d', 0, false], ['USE d', 0, false]];
            foreach ($expressions as $expression) {
                $insert = 'INSERT IGNORE INTO t VALUES (' . $expression . ',DEFAULT)';
                array_push($inputs, [$insert, 0, false], ['CREATE TABLE t(a INT,b INT)', 0, false], [$insert, 0, false], [$insert, 0, true], ['DROP TABLE t', 0, false]);
            }
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<array{string, int, bool}> $inputs Ordinary and prepared input histories
     */
    #[DataProvider('providerSql')]
    public function testInputLoweringKeepsTableAndOperandDiagnosticOrder(string $release, array $inputs): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            $expected = array_map(static fn (array $input): string => serialize((new Native())->observe($native, ...$input)), $inputs);
            $actual = array_map(static fn (array $input): string => serialize((new Native())->observe($memory, ...$input)), $inputs);
            self::assertSame($expected, $actual);
        } finally {
            $server->stop();
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<Scalar>}>
     */
    public static function providerValues(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $values = [new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')])];
            if ($release !== '5.7.44') {
                $values[] = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec());
                $values[] = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')], over: new M\Call\Window\WindowSpec());
            }
            yield $release => [$release, $values];
        }
    }

    /**
     * @param list<Scalar> $values Grouped operands retained across branch and table states
     */
    #[DataProvider('providerValues')]
    public function testStoredValuesKeepTheirConditionalRoleAndSubqueriesKeepTheirOwnScope(string $release, array $values): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $profile = $session->semantics()->profile();
                $session->text = 'opaque request label';
                array_map(static function (Scalar $value, int $index) use ($native, $session, $profile): void {
                    $into = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t'))));
                    $write = new M\Routine\Program\Write\DeferredInsertRows($into, [new M\Routine\Program\Write\DeferredInsertedRow([$value])]);
                    $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$write]);
                    $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $index)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
                    $expected = (new Native())->observe($native, (new Reference())->sql($create, $profile));
                    self::assertSame($expected, (new Memory())->observe($session, $create));
                    self::assertArrayNotHasKey('error', $expected);
                    $call = new M\Dml\ProcedureCall($create->name);
                    $history = array_map(static function (string $setup) use ($native, $session, $profile, $call): array {
                        $native->query($setup);
                        $session->query($setup);
                        $session->text = 'opaque request label';
                        $expected = (new Native())->observe($native, (new Reference())->sql($call, $profile));
                        self::assertSame($expected, (new Memory())->observe($session, $call));
                        return $expected;
                    }, ['SET @run=0', 'SET @run=1', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(7)', 'DROP TABLE t', 'SET @run=0']);
                    $problem = (new \SqlSemantics\Platform\MySql\Rules\Dml\Insert\ValueGroups())->problem($value);
                    self::assertNotNull($problem);
                    $code = $problem->windowName() === null ? 1111 : 3593;
                    self::assertSame([null, 1146, $code, $code, 1146, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                    $native->query('CREATE TABLE t(a INT)');
                    $session->query('CREATE TABLE t(a INT)');
                    $session->text = 'opaque request label';
                    $query = new M\Query\Select([], [new M\Query\SelectExpression($value)]);
                    $insert = new M\Dml\Insert\InsertRows($into, [new M\Dml\Insert\InsertedRow([new M\Expression\Subquery\ScalarSubquery($query)])]);
                    $expected = (new Native())->observe($native, (new Reference())->sql($insert, $profile));
                    self::assertSame($expected, (new Memory())->observe($session, $insert));
                    self::assertArrayNotHasKey('error', $expected);
                    self::assertSame($value, $write->rows[0]->values[0]);
                    $native->query('DROP TABLE t');
                    $session->query('DROP TABLE t');
                }, $values, array_keys($values));
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
