<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\LegacyProfileResponse;
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
final class ProfileStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, M\Utility\Show\Session\ShowProfile, M\Statement, M\Statement, bool}>
     */
    public static function providerStructures(): iterable
    {
        $profile = new M\Utility\Show\Session\ShowProfile([M\Utility\Show\Session\ProfileSection::Memory, M\Utility\Show\Session\ProfileSection::All, M\Utility\Show\Session\ProfileSection::Memory], limit: new M\Query\Clause\RowLimit(new M\Query\Clause\ProgramVariable(new Name('12345'))));
        $variable = new M\Routine\Program\VariableDeclaration([new Name('12345')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int), default: new M\Literal\NumberLiteral('0'));
        $selection = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $skipped = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\BooleanLiteral(false), [$selection])]);
        $name = new QualifiedName(new Name('p'));
        $parameters = new M\Routine\ParameterList([]);
        $cases = [
            [new M\Routine\CreateProcedure($name, $parameters, new M\Routine\Program\Block([$variable], [$profile])), new M\Dml\ProcedureCall($name), true],
            [new M\Routine\CreateProcedure($name, $parameters, new M\Routine\Program\Block([$variable], [$skipped, $profile])), new M\Dml\ProcedureCall($name), false],
            [new M\Routine\CreateFunction($name, $parameters, new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Routine\Definition\FunctionImplementation(new M\Routine\Program\Block([$variable], [$profile, new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('1'))]), [new M\Routine\Characteristic\Determinism(true)])), new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Call\FunctionCall(new Name('p')))])), true],
        ];
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([false, true] as $prepared) {
                foreach ($cases as $index => [$create, $invoke, $disconnected]) {
                    yield $release . ':' . (int) $prepared . ':' . $index => [$release, $prepared, $profile, $create, $invoke, $disconnected];
                }
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testUnchangedProfileOperandsRetainTheirScopeAndTheirResponseBoundary(string $release, bool $prepared, M\Utility\Show\Session\ShowProfile $profile, M\Statement $create, M\Statement $invoke, bool $disconnected): void
    {
        self::assertContains($profile, (new Walker())->find($create, $profile::class));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $selection = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]));
                $steps = [$profile, $create, $invoke, $selection, $profile];
                $history = array_map(static function (M\Statement $node, int $index) use ($native, $session, $prepared, $release, $create): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql, prepared: $prepared && $index === 2);
                    $session->text = 'opaque request label';
                    $actual = (new Memory())->observe($session, $node, prepared: $prepared && $index === 2);
                    $compare = $index === 2 && $create instanceof M\Routine\CreateFunction;
                    $contract = new LegacyProfileResponse();
                    return [serialize($compare ? $contract->comparable($expected, $release, $prepared) : $expected), serialize($compare ? $contract->comparable($actual, $release, $prepared) : $actual), $expected['error'][0] ?? null, $expected['disconnected'], [$expected, $actual]];
                }, $steps, array_keys($steps));

                self::assertSame(array_column($history, 0), array_column($history, 1), var_export(array_column($history, 4), true));
                self::assertSame([1327, null, null, $disconnected ? 2006 : null, $disconnected ? 2006 : 1327], array_column($history, 2));
                self::assertSame([false, false, $disconnected, $disconnected, $disconnected], array_column($history, 3));
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
