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
final class DeclarationStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\CreateProcedure, int}>
     */
    public static function providerStructures(): iterable
    {
        $column = static fn (): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $key = static fn (): M\Table\Key\IndexDefinition => new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Primary, [new M\Table\Key\ColumnPart(new Name('a'))]);
        $cases = [
            'columns' => [[$column(), $column()], 1060],
            'primary' => [[$column(), $key(), $key()], 1068],
            'index' => [[$column(), new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('a'))])], 1060],
            'reference' => [[$column(), new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('b'))])], 1072],
        ];
        foreach ($cases as $case => [$elements, $code]) {
            $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), $elements, ifNotExists: true);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$definition]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
            foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $case => [$release, $create, $code];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testTheSameDeclarationKeepsItsOperandsAcrossDestinationStates(string $release, M\Routine\CreateProcedure $create, int $code): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $expected = (new Native())->observe($native, (new Reference())->sql($create, $session->semantics()->profile()));
                $session->text = 'unrelated invalid input';
                self::assertSame($expected, (new Memory())->observe($session, $create));
                self::assertArrayNotHasKey('error', $expected);
                $call = new M\Dml\ProcedureCall($create->name);
                $history = array_map(static function (string $setup) use ($native, $session, $call): array {
                    array_map(static function (string $sql) use ($native, $session): void {
                        $native->query($sql);
                        $session->query($sql);
                    }, explode('; ', $setup));
                    $expected = (new Native())->observe($native, (new Reference())->sql($call, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    return [serialize($expected), serialize((new Memory())->observe($session, $call)), $expected['error'][0] ?? null];
                }, ['SET @active=0', 'SET @active=1', 'CREATE TABLE t(a INT); INSERT INTO t VALUES(7)', 'DROP TABLE t', 'SET @active=0']);
                self::assertSame([null, $code, str_starts_with($release, '5.') ? $code : null, $code, null], array_column($history, 2));
                self::assertSame(array_column($history, 0), array_column($history, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
