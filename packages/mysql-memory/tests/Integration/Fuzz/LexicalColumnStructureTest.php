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
final class LexicalColumnStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Statement>}>
     */
    public static function providerStructures(): iterable
    {
        $structures = [];
        foreach (['scalar', 'row2', 'row3', 'update', 'delete'] as $role) {
            $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('source')))))]);
            $operand = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))));
            if ($role === 'row2' || $role === 'row3') {
                $row = new M\Expression\Row(array_map(static fn (int $number): M\Literal\NumberLiteral => new M\Literal\NumberLiteral((string) $number), range(1, $role === 'row2' ? 2 : 3)));
                $operand = new M\Expression\Comparison(M\Expression\ComparisonOperator::Equal, $row, $operand);
            }
            $structures[] = match ($role) {
                'update' => new M\Dml\Update($with, false, false, [new M\Relation\TableReference(new QualifiedName(new Name('target')))], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), $operand)]),
                'delete' => new M\Dml\Delete($with, [], new M\Dml\WriteTarget(new QualifiedName(new Name('target'))), $operand),
                'scalar', 'row2', 'row3' => new M\QueryRoot(new M\Query\QueryExpression($with, new M\Query\Select([], [new M\Query\SelectExpression($operand)]))),
            };
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Statement> $structures Unchanged reads and writes with open catalog widths
     */
    #[DataProvider('providerStructures')]
    public function testTheSameStructuresRetainTheirOperandRolesAcrossColumnChanges(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $history = array_map(static function (string $setup) use ($native, $session, $structures): array {
                    $native->query($setup);
                    $session->query($setup);
                    return array_map(static function (M\Statement $structure) use ($native, $session): array {
                        $native->query('DELETE FROM target');
                        $session->query('DELETE FROM target');
                        $native->query('INSERT INTO target VALUES(1)');
                        $session->query('INSERT INTO target VALUES(1)');
                        $reference = (new Native())->observe($native, (new Reference())->sql($structure, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        return [serialize($reference), serialize((new Memory())->observe($session, $structure)), $reference['error'][0] ?? null];
                    }, $structures);
                }, ['CREATE TABLE target(a INT)', 'CREATE TABLE source(a INT)', 'INSERT INTO source VALUES(1)', 'ALTER TABLE source ADD COLUMN b INT DEFAULT 2', 'ALTER TABLE source ADD COLUMN c INT DEFAULT 3', 'DROP TABLE source']);
                self::assertSame(array_map(static fn (array $phase): array => array_column($phase, 0), $history), array_map(static fn (array $phase): array => array_column($phase, 1), $history));
                self::assertSame([[1146, 1146, 1146, 1146, 1146], [null, 1241, 1241, null, null], [null, 1241, 1241, null, null], [1241, null, 1241, 1241, 1241], [1241, 1241, null, 1241, 1241], [1146, 1146, 1146, 1146, 1146]], array_map(static fn (array $phase): array => array_column($phase, 2), $history));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
