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
 * The same stored conversion structure can be skipped, reached and skipped again.
 */
#[CoversNothing]
#[Medium]
final class DeferredTimeZoneProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int<0, 19>, string}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['plain', 'not', 'minus', 'null', 'not-null', 'cast', 'charset', 'if', 'case', 'zone'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Reuses one directly constructed invocation while changing only session history.
     *
     * @param int<0, 19> $seed The generated history index
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesUnchangedProgramStructurePreservesReachedAndSkippedOutcomes(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-time-zone:' . $seed);
        $operand = new NumberLiteral((string) $choices->integer(0, 1000));
        $operand = match ($seed % 4) {
            1 => new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast($operand, new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed)),
            2 => new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), $operand),
            3 => new \SqlSemantics\Platform\MySql\Statement\Call\ClockCall(\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now),
            0 => $operand,
        };
        $zone = $form === 'zone' ? ['10', 'Etc/UTC', '-00:00', 'utc'][$choices->integer(0, 3)] : '+00:00';
        $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone($form === 'zone' ? new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral() : $operand, new \SqlSemantics\Platform\MySql\Statement\Literal\Text($zone), $choices->integer(0, 1) === 1, (string) $choices->integer(0, 6));
        $value = match ($form) {
            'charset' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion($value, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new Name('utf8mb4'))),
            'if' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::If, [new NumberLiteral('0'), $value, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]),
            'case' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new NumberLiteral('0'), $value)]),
            'cast' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast($value, new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char)),
            'not' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNot($value),
            'minus' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, $value),
            'null', 'not-null' => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNullTest($value, $form === 'not-null'),
            default => $value,
        };
        $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($value)]);
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $memory = new MemoryClient();
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        $code = $form === 'zone' ? 1298 : 3998;
        $message = $form === 'zone' ? "Unknown or incorrect time zone: '" . $zone . "'" : 'Cannot cast value to TIMESTAMP WITH TIME ZONE.';
        $conditions = [['Error', (string) $code, $message]];
        foreach ([0, 1, 0] as $run) {
            self::assertArrayNotHasKey('error', $memory->observe($session, new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))])));
            $outcome = $memory->observe($session, $call);
            if ($run === 0) {
                self::assertArrayNotHasKey('error', $outcome);
                self::assertSame([], $outcome['warnings']);
            } else {
                self::assertSame([$code, 'HY000', $message], $outcome['error'] ?? null);
                self::assertSame($conditions, $outcome['warnings']);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
