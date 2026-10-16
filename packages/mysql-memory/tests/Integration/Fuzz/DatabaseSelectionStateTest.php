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
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabaseSelectionStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>, list<list<int|null>>}>
     */
    public static function providerStructures(): iterable
    {
        $table = new QualifiedName(new Name('t'), new Name('selection_domain'));
        $statements = [
            new MultipleDelete(null, [], [new QualifiedName(new Name('t'))], MultipleDeleteForm::BeforeFrom, [new TableReference($table)]),
            new MultipleDelete(null, [], [new QualifiedName(new Name('x'))], MultipleDeleteForm::BeforeFrom, [new TableReference($table)]),
            new ShowFunctionCode(new QualifiedName(new Name('f'))),
            new ShowProcedureCode(new QualifiedName(new Name('p'))),
            new CreateEvent(new QualifiedName(new Name('e')), new RecurringSchedule(new NumberLiteral('1'), IntervalUnit::Day), new Evaluation([new SelectExpression(new NumberLiteral('0'))])),
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements, [[1046, 1046, 1046, 1046, 1046], [null, 1109, 1289, 1289, null], [1046, 1046, 1046, 1046, 1046]]];
        }
    }

    /**
     * @param list<Statement> $statements The same requests before selection, after selection and after dropping the selected database
     * @param list<list<int|null>> $expectedCodes Every request's native error through the history
     */
    #[DataProvider('providerStructures')]
    public function testSameObjectsFollowDatabaseSelectionAndRemoval(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE selection_domain');
                $native->query('CREATE TABLE selection_domain.t(a INT)');
                $native->query('INSERT INTO selection_domain.t VALUES (1)');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE selection_domain; CREATE TABLE selection_domain.t(a INT); INSERT INTO selection_domain.t VALUES (1); SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (Statement $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'USE selection_domain', 'DROP DATABASE selection_domain']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): array => array_column($state, 2), $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
