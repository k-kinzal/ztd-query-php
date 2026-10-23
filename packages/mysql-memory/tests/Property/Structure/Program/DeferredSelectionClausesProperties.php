<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Fake\MemoryClient;
use Tests\Generator\Choices;

/**
 * The same stored composition structure can be skipped, reached and skipped again.
 */
#[CoversNothing]
#[Medium]
final class DeferredSelectionClausesProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int<0, 19>, 'projection'|'where'|'group'|'having'|'order'}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['projection', 'where', 'group', 'having', 'order'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Reuses one directly constructed invocation while changing only session history.
     *
     * @param int<0, 19> $seed The generated history index
     * @param 'projection'|'where'|'group'|'having'|'order' $form The retained expression role
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesUnchangedProgramStructurePreservesReachedAndSkippedOutcomes(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-selection-clauses:' . $seed);
        $width = $choices->integer(2, 4);
        $items = [];
        for ($index = 0; $index < $width; $index++) {
            $items[] = new NumberLiteral((string) $choices->integer(0, 1000));
        }
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row($items);
        $ordinary = [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('1'))];
        $source = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('fixture')));
        $order = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row);
        $body = match ($form) {
            'projection' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)], $source, options: [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new NumberLiteral('0'))),
            'where' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection($ordinary, $source, $row),
            'group' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection($ordinary, $source, groupBy: new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping([$order])),
            'having' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection($ordinary, $source, having: $row),
            'order' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $ordinary, $source, orderBy: [$order])),
        };
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE fixture(a INT); INSERT INTO fixture VALUES(1),(2)');
        $session->text = 'unrelated invalid input';
        $memory = new MemoryClient();
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        $conditions = [['Error', '1241', 'Operand should contain 1 column(s)']];
        foreach ([0, 1, 0] as $phase => $run) {
            self::assertArrayNotHasKey('error', $memory->observe($session, new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))])));
            $outcome = $memory->observe($session, $call);
            if ($run === 0) {
                self::assertArrayNotHasKey('error', $outcome);
                self::assertSame($release === '5.6.51' && $phase === 2 ? $conditions : [], $outcome['warnings']);
            } else {
                self::assertSame([1241, '21000', 'Operand should contain 1 column(s)'], $outcome['error'] ?? null);
                self::assertSame($conditions, $outcome['warnings']);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
