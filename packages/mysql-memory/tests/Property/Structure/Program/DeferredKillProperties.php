<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Fake\MemoryClient;
use Tests\Generator\Choices;

/**
 * The same stored KILL structure can be skipped, reached and skipped again.
 */
#[CoversNothing]
#[Medium]
final class DeferredKillProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Reuses one directly constructed invocation while changing only session history.
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesUnchangedProgramStructurePreservesReachedAndSkippedOutcomes(string $release, int $seed): void
    {
        $choices = new Choices('deferred-kill:' . $seed);
        $elements = array_map(static fn () => new NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4)));
        $row = new Row($elements);
        $process = $choices->integer(0, 1) === 0 ? $row : new DeferredLike(new NumberLiteral('1'), $row);
        $body = new DeferredKill($choices->one([null, ...\SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope::cases()]), $process);
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $memory = new MemoryClient();
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        $conditions = [['Error', '1241', 'Operand should contain 1 column(s)'], ['Error', '1204', 'You may only use constant expressions with SET']];
        foreach ([0, 1, 0] as $step => $run) {
            self::assertArrayNotHasKey('error', $memory->observe($session, new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))])));
            $outcome = $memory->observe($session, $call);
            if ($run === 0) {
                self::assertArrayNotHasKey('error', $outcome);
                self::assertSame($release === '5.6.51' && $step === 2 ? $conditions : [], $outcome['warnings']);
            } else {
                self::assertSame([1241, '21000', 'Operand should contain 1 column(s)'], $outcome['error'] ?? null);
                self::assertSame($conditions, $outcome['warnings']);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
