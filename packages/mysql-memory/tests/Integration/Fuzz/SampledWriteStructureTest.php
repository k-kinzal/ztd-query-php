<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Evaluation\Compile\Walker;
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
final class SampledWriteStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\Program\ProgramStatement, M\Routine\CreateProcedure}>
     */
    public static function providerInstructions(): iterable
    {
        $name = new QualifiedName(new Name('t'));
        $target = new M\Relation\TableReference($name);
        $source = new M\Relation\TableReference(new QualifiedName(new Name('u')), sample: new M\Relation\Hint\TableSample(M\Relation\Hint\SamplingMethod::System, new M\Literal\NumberLiteral('5')));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], $source);
        $instructions = [
            new M\Routine\Program\Write\DeferredUpdate(null, false, false, [$target], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Expression\Subquery\ScalarSubquery($query))]),
            new M\Routine\Program\Write\DeferredDelete(null, [], new M\Dml\WriteTarget($name), new M\Expression\Subquery\Exists($query)),
            new M\Routine\Program\Write\DeferredMultipleDelete(null, [], new M\Dml\Target\DeletionInput([$target, $source], [$name]), M\Dml\MultipleDeleteForm::BeforeFrom),
        ];
        foreach ($instructions as $instruction) {
            $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$instruction])]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
            foreach (['8.4.7', '9.0.1', '9.1.0'] as $release) {
                yield $release . ':' . $instruction::class => [$release, $instruction, $create];
            }
        }
    }

    #[DataProvider('providerInstructions')]
    public function testRetainedSamplingKeepsItsWholeInstructionAcrossInvocationStates(string $release, M\Routine\Program\ProgramStatement $instruction, M\Routine\CreateProcedure $create): void
    {
        self::assertContains($instruction, (new Walker())->find($create, $instruction::class));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $reference = new Reference();
                self::assertSame((new Native())->observe($native, $reference->sql($create, $session->semantics()->profile())), (new Memory())->observe($session, $create));
                $call = new M\Dml\ProcedureCall($create->name);
                $history = array_map(static function (string $setup) use ($native, $session, $call, $reference): array {
                    $native->query($setup);
                    $session->query($setup);
                    $expected = (new Native())->observe($native, $reference->sql($call, $session->semantics()->profile()));
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $call));
                    return $expected;
                }, ['SET @run=0', 'SET @run=1', 'CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)', 'SET @run=0']);
                self::assertSame([null, 1146, 1146, 6033, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
