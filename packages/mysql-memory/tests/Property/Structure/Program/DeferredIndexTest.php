<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Direct retained index structures remain dormant until the same program reaches them.
 */
#[CoversNothing]
#[Medium]
final class DeferredIndexTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, 'index'|'alter'|'table'}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['index', 'alter', 'table'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Uses the same typed declaration and invocation without parsing SQL at any phase.
     *
     * @param 'index'|'alter'|'table' $form The retained definition role
     */
    #[DataProvider('providerHistories')]
    public function testRetainedIndexInstructionsPreserveSkippedAndReachedStates(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-index:' . $seed);
        $values = array_map(static fn (): M\Literal\NumberLiteral => new M\Literal\NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4)));
        $part = new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row($values));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [$part], new M\Name\ColumnName(new Name('i')));
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $body = match ($form) {
            'index' => new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [$part]),
            'alter' => new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\AddConstraint($index)]),
            'table' => new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('fresh')), [$column, $index]),
        };
        $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$body]);
        $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        $call = new M\Dml\ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $memory = new Memory();
        self::assertArrayNotHasKey('error', $memory->observe($session, new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column])));
        $session->text = 'unrelated invalid input';
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        foreach ([0, 1, 0] as $run) {
            self::assertArrayNotHasKey('error', $memory->observe($session, new M\Utility\Set\SetVariables([new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('run')), new M\Literal\NumberLiteral((string) $run))])));
            $outcome = $memory->observe($session, $call);
            if ($run === 0) {
                self::assertArrayNotHasKey('error', $outcome);
                self::assertSame([], $outcome['warnings']);
            } else {
                self::assertSame([3800, 'HY000', "Expression of functional index 'i' cannot refer to a row value."], $outcome['error'] ?? null);
                self::assertSame([['Error', '3800', "Expression of functional index 'i' cannot refer to a row value."]], $outcome['warnings']);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(M\Routine\Program\Flow\IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }
}
