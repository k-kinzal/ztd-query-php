<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Fake\MemoryClient;
use Tests\Generator\Choices;

/**
 * Direct retained index structures remain dormant until the same program reaches them.
 */
#[CoversNothing]
#[Medium]
final class SpatialKeyArityProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int, 'index'|'alter'|'table'}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesRetainedIndexInstructionsPreserveSkippedAndReachedStates(string $release, int $seed, string $form): void
    {
        $choices = new Choices('spatial-key-arity:' . $seed);
        $name = new Name('spatial_key');
        $parts = array_map(static fn (int $number): M\Table\Key\ColumnPart => new M\Table\Key\ColumnPart(new Name($seed % 2 === 0 ? 'a' : 'c' . $number)), range(1, $choices->integer(2, 5)));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, $parts, new M\Name\ColumnName($name));
        $columns = array_map(static fn (string $name): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name($name)), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int))), array_values(array_unique(array_map(static fn (M\Table\Key\ColumnPart $part): string => $part->column->value, $parts))));
        $body = match ($form) {
            'index' => new M\Routine\Program\Write\Index\DeferredCreateIndex($name, new QualifiedName(new Name('t')), $parts, M\Table\Key\IndexKind::Spatial),
            'alter' => new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\AddConstraint($index)]),
            'table' => new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('fresh')), [...$columns, $index], [new M\Table\Option\EngineOption(new Name('MyISAM'))]),
        };
        $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$body]);
        $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        $call = new M\Dml\ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $memory = new MemoryClient();
        self::assertArrayNotHasKey('error', $memory->observe($session, new M\Table\CreateTable(new QualifiedName(new Name('t')), $columns, [new M\Table\Option\EngineOption(new Name('MyISAM'))])));
        $session->text = 'unrelated invalid input';
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        $error = $release === '5.6.51' ? [1210, 'HY000', 'Incorrect arguments to SPATIAL INDEX'] : [1070, '42000', 'Too many key parts specified; max 1 parts allowed'];
        $warning = ['Error', (string) $error[0], $error[2]];
        foreach ([0, 1, 0] as $phase => $run) {
            self::assertArrayNotHasKey('error', $memory->observe($session, new M\Utility\Set\SetVariables([new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('run')), new M\Literal\NumberLiteral((string) $run))])));
            $outcome = $memory->observe($session, $call);
            if ($run === 0) {
                self::assertArrayNotHasKey('error', $outcome);
                self::assertSame($release === '5.6.51' && $phase === 2 ? [$warning] : [], $outcome['warnings']);
            } else {
                self::assertSame($error, $outcome['error'] ?? null);
                self::assertSame([$warning], $outcome['warnings']);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(M\Routine\Program\Flow\IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }
}
