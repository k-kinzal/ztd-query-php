<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
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
final class DeferredEvaluationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d'];
            $expressions = ['(1,2)', 'missing,(1,2)', '(1,2),missing', '(1,2),(SELECT a FROM fixture)', '(1,2)+INTERVAL 1 DAY', '1+INTERVAL (1,2) DAY', 'INTERVAL (1,2) DAY+1', 'INTERVAL 1 DAY+(1,2)', '(1,2)+INTERVAL missing DAY', 'missing+INTERVAL (1,2) DAY', 'INTERVAL (1,2) DAY+missing', 'INTERVAL missing DAY+(1,2)', '((1,2)+INTERVAL 1 DAY) IN (SELECT a FROM fixture)', 'DATE((1,2)+INTERVAL 1 DAY)=ALL(SELECT a FROM fixture)', '1+INTERVAL (SELECT a FROM fixture) DAY,(1,2)', '(1,2) OR 0', '0 AND (1,2)', '(1,2) XOR (SELECT a FROM fixture)', '(1,2)||1', '1+INTERVAL ((1,2)) DAY', 'INTERVAL ((1,2)) DAY+1', '1+INTERVAL ROW(1,2) DAY', 'INTERVAL ROW(1,2) DAY+1', 'missing+INTERVAL ((1,2)) DAY', 'INTERVAL ((1,2)) DAY+missing'];
            foreach ($expressions as $index => $expression) {
                array_push($statements, 'DO ' . $expression, 'CREATE PROCEDURE p' . $index . '(run INT) BEGIN IF run THEN DO ' . $expression . '; END IF; END', 'CALL p' . $index . '(0)', 'CALL p' . $index . '(1)');
            }
            array_push($statements, 'CREATE TABLE fixture(a INT)', 'INSERT INTO fixture VALUES(1),(2)');
            foreach ($expressions as $index => $expression) {
                array_push($statements, 'DO ' . $expression, 'CALL p' . $index . '(0)', 'CALL p' . $index . '(1)');
            }
            if (!str_starts_with($release, '5.')) {
                $statements[] = 'DO ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( DATE ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ROW ( ( NOT ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ( ~ ( CONCAT( ( LEAD ( ( ( ( ( ( ( ( CONVERT ( ( ( ( ( ( ( ( ( ( ( USER ( ) ) ) / ( ( SESSION_USER( ) ) ) ) / ( ( USER ( ) ) ) ) MOD ( ( USER ( ) ) ) ) % ( ( USER ( ) ) ) ) ^ ( ( SESSION_USER( ) ) ) ) LIKE ( SESSION_USER( ) ) ) ) IS NOT FALSE ) USING BINARY ) ) ) DIV ( ( USER ( ) ) ) ) & ( ( USER ( ) ) ) ) + ( ( SYSTEM_USER( ) ) ) ) IN ( ( ( ( ( ( SYSTEM_USER( ) ) ) ) ) ) ) ) ) IS FALSE ) OVER _sqlfaker_identifier ) , ( SESSION_USER( ) ) ) ) ) ) - ( ( SESSION_USER( ) ) ) ) ^ ( ( SYSTEM_USER( ) ) ) ) DIV ( ( USER ( ) ) ) ) % ( ( USER ( ) ) ) ) - INTERVAL ( ( ( ( ( SYSTEM_USER( ) ) ) ) ) ) DAY_HOUR ) - INTERVAL ( ( ( ( ( USER ( ) ) ) ) ) ) DAY_HOUR ) | ( ( SESSION_USER( ) ) ) ) + INTERVAL ( ( ( ( ( SESSION_USER( ) ) ) ) ) ) DAY_HOUR ) + ( ( SYSTEM_USER( ) ) ) ) << ( ( SESSION_USER( ) ) ) ) >> ( ( SESSION_USER( ) ) ) ) << ( ( USER ( ) ) ) ) | ( ( USER ( ) ) ) ) MOD ( ( SYSTEM_USER( ) ) ) ) % ( ( SYSTEM_USER( ) ) ) ) << ( ( SYSTEM_USER( ) ) ) ) ^ ( ( SYSTEM_USER( ) ) ) ) MOD ( ( SESSION_USER( ) ) ) ) + ( ( SYSTEM_USER( ) ) ) ) IN ( ( ( ( ( ( USER ( ) ) ) ) ) ) , ( ( ( ( USER ( ) ) ) ) ) ) ) ) IS NULL ) IS NULL ) IS NULL ) ) OR ( ( ( ( ( SESSION_USER( ) ) ) ) ) ) ) ) , ( ( ( ( SYSTEM_USER( ) ) ) ) ) ) ) ) - INTERVAL ( ( ( ( ( SYSTEM_USER( ) ) ) ) ) ) DAY_HOUR ) | ( ( USER ( ) ) ) ) DIV ( ( USER ( ) ) ) ) + ( ( SYSTEM_USER( ) ) ) ) - ( ( SYSTEM_USER( ) ) ) ) % ( ( SYSTEM_USER( ) ) ) ) ^ ( ( SESSION_USER( ) ) ) ) | ( ( USER ( ) ) ) ) ^ ( ( USER ( ) ) ) ) IN ( ( ( ( ( ( SESSION_USER( ) ) ) ) ) ) ) ) ) IS NOT NULL ) IS NULL ) = ALL ( TABLE _sqlfaker_identifier ) ) IS NOT NULL ) IS NOT NULL ) = ( ( ( SESSION_USER( ) ) ) ) ) = ( ( ( USER ( ) ) ) ) ) IS UNKNOWN ) OR ( ( ( ( ( SYSTEM_USER( ) ) ) ) ) ) ) ) ) & ( ( USER ( ) ) ) ) / ( ( SYSTEM_USER( ) ) ) ) MOD ( ( SESSION_USER( ) ) ) ) % ( ( SYSTEM_USER( ) ) ) ) | ( ( SYSTEM_USER( ) ) ) ) - ( ( USER ( ) ) ) ) * ( ( USER ( ) ) ) ) ^ ( ( USER ( ) ) ) ) / ( ( SESSION_USER( ) ) ) ) NOT IN ( TABLE _sqlfaker_identifier ) ) ) = ( ( ( USER ( ) ) ) ) ) IS NOT NULL ) = ALL ( TABLE _sqlfaker_identifier ) ) IS NULL ) IS NOT NULL ) IS NULL ) IS NOT NULL ) = ( ( ( SYSTEM_USER( ) ) ) ) ) = ( ( ( SESSION_USER( ) ) ) ) ) IS NULL ) = ALL ( TABLE _sqlfaker_identifier ) ) = ( ( ( SESSION_USER( ) ) ) ) ) IS NOT TRUE';
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testEvaluationPreservesDependenciesAndInvocationConditions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('deferred-evaluation-20261017');
        $structures = [];
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], new M\Relation\TableReference(new QualifiedName(new Name('fixture')))));
        foreach ([2, 3, 4] as $width) {
            $row = new M\Expression\Row(array_map(static fn (int $index): M\Literal\NumberLiteral => new M\Literal\NumberLiteral((string) $choices->integer(0, 9)), range(1, $width)));
            $expressions = [new M\Routine\Program\Expression\Operator\DeferredInterval($row, $query, M\Expression\IntervalUnit::Day), new M\Routine\Program\Expression\Operator\DeferredInterval($query, new M\Expression\GroupedRow($row), M\Expression\IntervalUnit::Month), new M\Routine\Program\Expression\Operator\DeferredInterval($query, new M\Expression\GroupedRow($row), M\Expression\IntervalUnit::Year, leading: true)];
            foreach (M\Expression\LogicalOperator::cases() as $operator) {
                $expressions[] = new M\Routine\Program\Expression\Operator\DeferredLogical($operator, $row, $query);
            }
            foreach ($expressions as $expression) {
                $body = new M\Routine\Program\DeferredEvaluation([new M\Routine\Program\DeferredProjection($expression)]);
                $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('typed' . count($structures))), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$body])]));
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures
     */
    #[DataProvider('providerStructures')]
    public function testDirectStructuresPreserveSkippedReachedAndPopulatedStates(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance($release))->connect();
            array_map(static function (string $sql) use ($native, $session): void {
                $native->query($sql);
                $session->query($sql);
            }, ['CREATE DATABASE d', 'USE d']);
            $definitions = array_map(static function (M\Routine\CreateProcedure $structure) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($structure, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                return [(new Native())->observe($native, $sql), (new \Fuzz\Structure\Audit\Memory())->observe($session, $structure)];
            }, $structures);
            self::assertSame(array_map(serialize(...), array_column($definitions, 0)), array_map(serialize(...), array_column($definitions, 1)));
            $calls = array_map(static fn (M\Routine\CreateProcedure $structure): M\Dml\ProcedureCall => new M\Dml\ProcedureCall($structure->name), $structures);
            $states = [['SET @run=0'], ['SET @run=1'], ['CREATE TABLE fixture(a INT)', 'INSERT INTO fixture VALUES(1),(2)'], ['SET @run=0']];
            $observations = array_map(static function (array $history) use ($native, $session, $calls): array {
                array_map(static function (string $sql) use ($native, $session): void {
                    $native->query($sql);
                    $session->query($sql);
                }, $history);
                return array_map(static function (M\Dml\ProcedureCall $call) use ($native, $session): array {
                    $sql = (new \Fuzz\Structure\Reference())->sql($call, $session->semantics()->profile());
                    $session->text = 'unrelated invalid input';
                    $pair = [(new Native())->observe($native, $sql), (new \Fuzz\Structure\Audit\Memory())->observe($session, $call)];
                    self::assertSame('unrelated invalid input', $session->text);
                    return $pair;
                }, $calls);
            }, $states);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
