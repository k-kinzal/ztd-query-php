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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StoredRowConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>}>
     */
    public static function providerHistories(): iterable
    {
        $history = [];
        foreach (['row', 'arithmetic', 'grouped_arithmetic', 'membership', 'native_call'] as $operandKind) {
            foreach (['if', 'while', 'repeat', 'initial', 'case', 'case_both', 'case_when'] as $kind) {
                $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
                if ($operandKind === 'native_call') {
                    $row = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall(new Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument($row)]);
                } elseif ($operandKind === 'membership') {
                    $row = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership(new NumberLiteral('0'), [$row]);
                } elseif ($operandKind !== 'row') {
                    $row = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new NumberLiteral('1'), $row);
                    if ($operandKind === 'grouped_arithmetic') {
                        $row = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped($row);
                    }
                }
                $noop = new Evaluation([new SelectExpression(new NumberLiteral('0'))]);
                $otherwise = new Evaluation([new SelectExpression(new NumberLiteral('0'))]);
                $instruction = match ($kind) {
                    'if' => new IfStatement([new ConditionalBranch($row, [$noop])]),
                    'while' => new WhileLoop($row, [$noop]),
                    'repeat' => new RepeatLoop($row, [$noop]),
                    'case' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase($row, [new ConditionalBranch(new NumberLiteral('1'), [$noop])], [$otherwise]),
                    'case_both' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase($row, [new ConditionalBranch(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), [$noop])], [$otherwise]),
                    'case_when' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase(new NumberLiteral('1'), [new ConditionalBranch($row, [$noop])], [$otherwise]),
                    'initial' => new Block([new VariableDeclaration([new Name('x')], new Elementary(ElementaryKind::Boolean), default: $row)], [$noop]),
                };
                $history[] = new CreateProcedure(new QualifiedName(new Name('p_' . $operandKind . '_' . $kind)), new ParameterList([]), new IfStatement([new ConditionalBranch(new UserVariable(new Name('run')), [$instruction])]));
                $call = new ProcedureCall(new QualifiedName(new Name('p_' . $operandKind . '_' . $kind)));
                foreach (['0', '1', '0'] as $run) {
                    $history[] = new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral($run))]);
                    $history[] = $call;
                }
            }
        }
        $returned = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $body = new Block([], [new IfStatement([new ConditionalBranch(new UserVariable(new Name('run')), [$returned])]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new NumberLiteral('7'))]);
        $history[] = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new QualifiedName(new Name('f_return')), new ParameterList([]), new Elementary(ElementaryKind::Boolean), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation($body, [new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism(true)]));
        $call = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('f_return')))]));
        foreach (['0', '1', '0'] as $run) {
            $history[] = new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral($run))]);
            $history[] = $call;
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $history];
        }
    }

    /**
     * @param list<Statement> $history Direct definitions followed by repeated calls to the same stored body
     */
    #[DataProvider('providerHistories')]
    public function testTheSameStoredRowsFollowSessionControlState(string $release, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $snapshot = array_map(static fn (Statement $node): string => (new Reference())->sql($node, $session->semantics()->profile()), $history);
                $observed = array_map(static function (Statement $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'Unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $history);
                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame(array_merge(...array_fill(0, 36, [null, null, null, null, 1241, null, null])), array_column($observed, 2));
                self::assertSame($snapshot, array_map(static fn (Statement $node): string => (new Reference())->sql($node, $session->semantics()->profile()), $history));
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
