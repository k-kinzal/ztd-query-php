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
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StoredValuesStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testUnchangedDefinitionsKeepSkippedAndReachedQueryDependencies(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $input = new M\Relation\TableReference(new QualifiedName(new Name('fixture')));
            $subquery = new M\Expression\Subquery\RowSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a'))), new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('b')))], $input));
            $codes = new M\Routine\Program\Expression\Call\DeferredChar([$subquery]);
            $values = new M\Query\With\DeferredValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('3')]), new M\Query\With\DeferredValueRow([$codes])]);
            $ordinary = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('7')])]);
            $queries = [$values, new M\Query\ParenthesizedQuery($values), new M\Query\Set\SetOperation($ordinary, M\Query\Set\SetOperator::Union, null, $values)];
            $stored = array_map(static fn ($query): M\Routine\Program\Selection\DeferredQuery => new M\Routine\Program\Selection\DeferredQuery($query), $queries);
            $bodies = array_merge(...array_map(static fn ($query): array => [new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$query])]), new M\Routine\Program\Block([new M\Routine\Program\Cursor\CursorDeclaration(new Name('cur'), $query)], [new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [new M\Routine\Program\Cursor\OpenCursor(new Name('cur')), new M\Routine\Program\Cursor\CloseCursor(new Name('cur'))])])])], $stored));
            $definitions = array_map(static fn ($body, int $index): M\Routine\CreateProcedure => new M\Routine\CreateProcedure(new QualifiedName(new Name('typed' . $index)), new M\Routine\ParameterList([]), $body), $bodies, array_keys($bodies));
            array_map(static function (string $sql) use ($native, $session): void {
                $native->query($sql);
                $session->query($sql);
            }, ['CREATE DATABASE read_domain', 'USE read_domain']);
            $created = array_map(static function (M\Routine\CreateProcedure $definition) use ($native, $session): array {
                $session->text = 'unrelated invalid input';
                return [(new Native())->observe($native, (new Reference())->sql($definition, $session->semantics()->profile())), (new Memory())->observe($session, $definition)];
            }, $definitions);
            self::assertSame(array_map(serialize(...), array_column($created, 0)), array_map(serialize(...), array_column($created, 1)));
            $calls = array_map(static fn (M\Routine\CreateProcedure $definition): M\Dml\ProcedureCall => new M\Dml\ProcedureCall($definition->name), $definitions);
            $observations = array_map(static function (string $history) use ($native, $session, $calls): array {
                $native->query($history);
                $session->query($history);
                return array_map(static function (M\Dml\ProcedureCall $call) use ($native, $session): array {
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, (new Reference())->sql($call, $session->semantics()->profile())), (new Memory())->observe($session, $call)];
                }, $calls);
            }, ['SET @run=0', 'SET @run=1', 'CREATE TABLE fixture(a INT)', 'ALTER TABLE fixture ADD COLUMN b INT', 'INSERT INTO fixture VALUES(1,2),(3,4)', 'SET @run=0']);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
