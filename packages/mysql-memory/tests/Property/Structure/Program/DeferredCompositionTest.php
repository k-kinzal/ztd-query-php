<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Choices;
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
use Tests\Result\Observation;

/**
 * The same stored composition structure can be skipped, reached and skipped again.
 */
#[CoversNothing]
#[Medium]
final class DeferredCompositionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int<0, 19>, 'cast'|'charset'|'if-result'|'if-condition'|'case-compared'|'case-condition'|'case-result'|'case-else'}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['cast', 'charset', 'if-result', 'if-condition', 'case-compared', 'case-condition', 'case-result', 'case-else'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Reuses one directly constructed invocation while changing only session history.
     *
     * @param int<0, 19> $seed The generated history index
     * @param 'cast'|'charset'|'if-result'|'if-condition'|'case-compared'|'case-condition'|'case-result'|'case-else' $form The retained expression role
     */
    #[DataProvider('providerHistories')]
    public function testUnchangedProgramStructurePreservesReachedAndSkippedOutcomes(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-composition:' . $seed);
        $width = $choices->integer(2, 4);
        $items = [];
        for ($index = 0; $index < $width; $index++) {
            $items[] = new NumberLiteral((string) $choices->integer(0, 1000));
        }
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row($items);
        $value = match ($form) {
            'cast' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast($row, new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char)),
            'charset' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion($row, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new Name('utf8mb4'))),
            'if-result' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::If, [new NumberLiteral('0'), $row, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]),
            'if-condition' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::If, [$row, new NumberLiteral('0'), new NumberLiteral('1')]),
            'case-compared' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase($row, [new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch(new NumberLiteral('1'), new NumberLiteral('0'))]),
            'case-condition' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch($row, new NumberLiteral('0'))]),
            'case-result' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new NumberLiteral('0'), $row)]),
            'case-else' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch(new NumberLiteral('1'), new NumberLiteral('0'))], $row),
        };
        $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($value)]);
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $memory = new Observation();
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
