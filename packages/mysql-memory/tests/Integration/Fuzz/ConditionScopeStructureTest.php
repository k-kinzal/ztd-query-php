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
final class ConditionScopeStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement, M\Routine\CreateProcedure, int}>
     */
    public static function providerScopes(): iterable
    {
        $signal = new M\Routine\Condition\Signal(new M\Routine\Condition\ConditionName(new Name('PRIMARY')));
        $resignal = new M\Routine\Condition\Resignal(new M\Routine\Condition\ConditionName(new Name('\\')));
        foreach ([$signal, $resignal] as $node) {
            self::assertInstanceOf(M\Routine\Condition\ConditionName::class, $node->condition);
            $declaration = new M\Routine\Program\ConditionDeclaration(new Name($node->condition->name->value), new M\Routine\Condition\SqlState(new M\Literal\Text('01000')));
            $body = $node instanceof M\Routine\Condition\Resignal
                ? new M\Routine\Program\Block([$declaration, new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [new M\Routine\Condition\SqlState(new M\Literal\Text('45000'))], $node)], [new M\Routine\Condition\Signal(new M\Routine\Condition\SqlState(new M\Literal\Text('45000')))])
                : new M\Routine\Program\Block([$declaration], [$node]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
            foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $node::class => [$release, $node, $create, 1319];
            }
        }
        $show = new M\Utility\Show\Session\ShowProfile([M\Utility\Show\Session\ProfileSection::Memory, M\Utility\Show\Session\ProfileSection::All, M\Utility\Show\Session\ProfileSection::Memory], limit: new M\Query\Clause\RowLimit(new M\Query\Clause\ProgramVariable(new Name('12345'))));
        $variable = new M\Routine\Program\VariableDeclaration([new Name('12345')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int), default: new M\Literal\NumberLiteral('0'));
        $profile = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Block([$variable], [$show]));
        $get = new M\Routine\Condition\Diagnostics\GetDiagnostics(new M\Routine\Condition\Diagnostics\ConditionDiagnostics(new M\Name\ColumnUse(new Name('A')), [new M\Routine\Condition\Diagnostics\InformationItem(new M\Variable\UserVariable(new Name('A1B2C')), M\Routine\Condition\ConditionItemName::ReturnedSqlstate)]), M\Routine\Condition\Diagnostics\DiagnosticsArea::Stacked);
        $number = new M\Routine\Program\VariableDeclaration([new Name('A')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int), default: new M\Literal\NumberLiteral('1'));
        $handler = new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [new M\Routine\Condition\SqlState(new M\Literal\Text('45000'))], $get);
        $diagnostics = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Block([$number, $handler], [new M\Routine\Condition\Signal(new M\Routine\Condition\SqlState(new M\Literal\Text('45000')))]));
        foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ': profile' => [$release, $show, $profile, 1327];
            yield $release . ': stacked' => [$release, $get, $diagnostics, 3004];
        }
    }

    #[DataProvider('providerScopes')]
    public function testTheOriginalConditionResolvesOnlyInItsOwningProgramScope(string $release, M\Statement $node, M\Routine\CreateProcedure $create, int $code): void
    {
        self::assertContains($node, (new Walker())->find($create, $node::class));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $steps = [$node, $create, new M\Dml\ProcedureCall($create->name), $node];
                $history = array_map(static function (M\Statement $statement) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($statement, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $statement);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $steps);
                self::assertSame([$code, null, null, $code], array_column($history, 2));
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
