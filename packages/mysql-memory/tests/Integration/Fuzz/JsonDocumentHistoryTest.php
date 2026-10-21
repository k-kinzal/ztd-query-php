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
final class JsonDocumentHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\Program\Selection\DeferredQuery, int}>
     */
    public static function providerDocuments(): iterable
    {
        $one = new M\Literal\NumberLiteral('1');
        foreach ([
            'row' => [new M\Expression\Row([$one, new M\Literal\NumberLiteral('2')]), 1241],
            'subquery' => [new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($one)])), 1210],
            'tableless-star' => [new M\Routine\Program\Expression\DeferredSubquery(new M\Query\Select([], [new M\Query\Star()])), 1096],
        ] as $index => [$document, $error]) {
            $table = new M\Relation\JsonTable($document, new M\Literal\StringLiteral(['$']), [new M\Call\Json\OrdinalityColumn(new Name('n'))], new Name('j'));
            $node = new M\Routine\Program\Selection\DeferredQuery(new M\Query\Select([], [new M\Query\Star()], $table));
            foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $index => [$release, $node, $error];
            }
        }
    }

    #[DataProvider('providerDocuments')]
    public function testTheSameDocumentIsPreparedOnlyWhenItsStoredInstructionIsReached(string $release, M\Routine\Program\Selection\DeferredQuery $node, int $code): void
    {
        $name = new QualifiedName(new Name('p'), new Name('d'));
        $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$node]);
        $create = new M\Routine\CreateProcedure($name, new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        self::assertSame($node, $branch->statements[0]);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->set_charset('utf8mb4');
                $session = (new Instance($release, [], ['d']))->connect();
                $session->query('SET NAMES utf8mb4');
                $reference = new Reference();
                $definition = (new Native())->observe($native, $reference->sql($create, $session->semantics()->profile()));
                $session->text = 'opaque request label';
                self::assertSame($definition, (new Memory())->observe($session, $create));
                self::assertArrayNotHasKey('error', $definition);
                $call = new M\Dml\ProcedureCall($name);
                $history = array_map(static function (int $run) use ($native, $session, $reference, $call): array {
                    $setup = 'SET @run=' . $run;
                    $assignment = $session->analyze($setup)->statement;
                    self::assertInstanceOf(M\Statement::class, $assignment);
                    self::assertSame((new Native())->observe($native, $setup), (new Memory())->observe($session, $assignment));
                    $expected = (new Native())->observe($native, $reference->sql($call, $session->semantics()->profile()));
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $call));
                    return $expected;
                }, [0, 1, 0]);
                self::assertSame([null, $code, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
