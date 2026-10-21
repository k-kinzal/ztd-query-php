<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use mysqli_result;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedConditionScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement, M\Routine\CreateProcedure, int, ?string}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerContexts(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([117, 118, 119] as $index) {
            $choices = new Choices('row-roles-20261017:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            \SqlSemantics\Diagnostic\Check::invariant($node instanceof M\Routine\Condition\Signal || $node instanceof M\Routine\Condition\Resignal || $node instanceof M\Routine\Condition\Diagnostics\GetDiagnostics, 'The recorded whole-model roots retain their statement identities.');
            $declarations = [];
            if ($node instanceof M\Routine\Condition\Signal || $node instanceof M\Routine\Condition\Resignal) {
                \SqlSemantics\Diagnostic\Check::invariant($node->condition instanceof M\Routine\Condition\ConditionName, 'The generated condition keeps its original named reference.');
                $declarations[] = new M\Routine\Program\ConditionDeclaration($node->condition->name, new M\Routine\Condition\SqlState(new M\Literal\Text('01000')));
            } else {
                $declarations[] = new M\Routine\Program\VariableDeclaration([new Name('A')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int), default: new M\Literal\NumberLiteral('1'));
            }
            if ($node instanceof M\Routine\Condition\Signal) {
                $statements = [$node];
            } else {
                $declarations[] = new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [new M\Routine\Condition\SqlState(new M\Literal\Text('45000'))], $node);
                $statements = [new M\Routine\Condition\Signal(new M\Routine\Condition\SqlState(new M\Literal\Text('45000')))];
            }
            $body = new M\Routine\Program\Block($declarations, $statements);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('pctx')), new M\Routine\ParameterList([]), $body);
            $initial = substr((new Profiles())->select($node)->grammar->value, 6);
            foreach (array_unique([$initial, '8.4.7', '9.1.0']) as $release) {
                yield $release . ':' . $index => [$release, $node, $create, $index === 117 ? 3004 : 1319, $index === 117 ? '45000' : null];
            }
        }
    }

    #[DataProvider('providerContexts')]
    public function testUnchangedConditionStructuresRunInTheirDeclaredScopeAndDoNotLeakIt(string $release, M\Statement $node, M\Routine\CreateProcedure $create, int $outsideError, ?string $expectedState): void
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
                $steps = [$node, $create, new M\Dml\ProcedureCall($create->name), $node];
                $history = array_map(static function (M\Statement $step) use ($native, $session, $profile): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($step, $profile));
                    self::assertSame($expected, (new Memory())->observe($session, $step));
                    return $expected;
                }, $steps);
                self::assertSame([$outsideError, null, null, $outsideError], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertContains($node, (new Walker())->find($create, $node::class));
                self::assertSame($expectedState, $session->variables->user('A1B2C')[0]);
                $value = $native->query('SELECT @A1B2C');
                self::assertInstanceOf(mysqli_result::class, $value);
                self::assertSame([$expectedState], $value->fetch_row());
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
