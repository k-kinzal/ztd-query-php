<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Choices;
use LogicException;
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
final class FunctionalIndexKindTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, 'index'|'alter'|'table', M\Table\Key\IndexKind}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['index', 'alter', 'table'] as $form) {
                    foreach ([M\Table\Key\IndexKind::Primary, M\Table\Key\IndexKind::FullText, M\Table\Key\IndexKind::Spatial] as $kind) {
                        if ($form !== 'index' || $kind !== M\Table\Key\IndexKind::Primary) {
                            yield $release . ':' . $seed . ':' . $form . ':' . $kind->value => [$release, $seed, $form, $kind];
                        }
                    }
                }
            }
        }
    }

    /**
     * Uses the same typed declaration and invocation without parsing SQL at any phase.
     *
     * @param 'index'|'alter'|'table' $form The retained definition role
     * @throws LogicException When the provider supplies an unconstrained index kind
     */
    #[DataProvider('providerHistories')]
    public function testRetainedIndexInstructionsPreserveSkippedAndReachedStates(string $release, int $seed, string $form, M\Table\Key\IndexKind $kind): void
    {
        $choices = new Choices('functional-kind:' . $seed);
        $name = new Name('i');
        $part = new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral((string) $choices->integer(0, 100)));
        $index = new M\Table\Key\IndexDefinition($kind, [$part], new M\Name\ColumnName($name));
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $body = match ($form) {
            'index' => new M\Routine\Program\Write\Index\DeferredCreateIndex($name, new QualifiedName(new Name('t')), [$part], $kind),
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
        $error = match ($kind) {
            M\Table\Key\IndexKind::Primary => [3756, 'HY000', 'The primary key cannot be a functional index'],
            M\Table\Key\IndexKind::FullText => [3759, 'HY000', 'Fulltext functional index is not supported.'],
            M\Table\Key\IndexKind::Spatial => [3760, 'HY000', 'Spatial functional index is not supported.'],
            M\Table\Key\IndexKind::Index, M\Table\Key\IndexKind::Unique => throw new LogicException('A functional-kind property uses constrained kinds.'),
        };
        foreach ([0, 1, 0] as $run) {
            self::assertArrayNotHasKey('error', $memory->observe($session, new M\Utility\Set\SetVariables([new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('run')), new M\Literal\NumberLiteral((string) $run))])));
            $outcome = $memory->observe($session, $call);
            if ($run === 0) {
                self::assertArrayNotHasKey('error', $outcome);
                self::assertSame([], $outcome['warnings']);
            } else {
                self::assertSame($error, $outcome['error'] ?? null);
                self::assertSame([['Error', (string) $error[0], $error[2]]], $outcome['warnings']);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(M\Routine\Program\Flow\IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }
}
