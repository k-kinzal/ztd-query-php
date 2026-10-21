<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Program\WriteOrderingHistory;
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
final class WriteOrderingStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $definitions = [];
        $target = new QualifiedName(new Name('t'));
        foreach (['0', '01', '2', '18446744073709551615'] as $digits) {
            $number = new M\Literal\NumberLiteral($digits);
            foreach ([$number, new M\Expression\Grouped(new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Plus, $number))] as $operand) {
                $ordering = [new M\Query\OrderItem($operand)];
                $where = new M\Name\ColumnUse(new Name('a'));
                $delete = new M\Routine\Program\Write\DeferredDelete(null, [], new M\Dml\WriteTarget($target), $where, $ordering);
                $update = new M\Routine\Program\Write\DeferredUpdate(null, false, false, [new M\Relation\TableReference($target)], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('b')), new M\Literal\NumberLiteral('1'))], $where, $ordering);
                foreach ([$delete, $update] as $write) {
                    $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$write]);
                    $definitions[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . count($definitions))), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $definitions];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $definitions Definitions retained across all invocations
     */
    #[DataProvider('providerStructures')]
    public function testTheSameStoredWritesResolveReachedDependenciesWithoutReadingInputText(string $release, array $definitions): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->query('USE d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                [$expected, $actual, $conditions] = (new WriteOrderingHistory())->observe($session, $native, $definitions);
                self::assertSame($expected, $actual);
                self::assertSame(array_fill(0, count($definitions), [null, 1146, 1054, 1054, null]), $conditions);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
