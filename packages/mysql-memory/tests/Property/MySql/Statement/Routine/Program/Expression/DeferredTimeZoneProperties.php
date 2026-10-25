<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Program\Expression;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNot;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNullTest;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class DeferredTimeZoneProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $value);
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, int<0, 19>, string}>
     */
    public static function providerDeferredTimeZoneHistories(): iterable
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
    #[DataProvider('providerDeferredTimeZoneHistories')]
    #[Test]
    public function satisfiesUnchangedProgramStructurePreservesReachedAndSkippedOutcomes(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-time-zone:' . $seed);
        $operand = new NumberLiteral((string) $choices->integer(0, 1000));
        $operand = match ($seed % 4) {
            1 => new Cast($operand, new CastTarget(CastKind::Signed)),
            2 => new Arithmetic(ArithmeticOperator::Plus, new NullLiteral(), $operand),
            3 => new ClockCall(Clock::Now),
            0 => $operand,
        };
        $zone = $form === 'zone' ? ['10', 'Etc/UTC', '-00:00', 'utc'][$choices->integer(0, 3)] : '+00:00';
        $value = new Model($form === 'zone' ? new NullLiteral() : $operand, new Text($zone), $choices->integer(0, 1) === 1, (string) $choices->integer(0, 6));
        $value = match ($form) {
            'charset' => new DeferredCharsetConversion($value, new CharsetName(new Name('utf8mb4'))),
            'if' => new DeferredKeywordCall(KeywordFunction::If, [new NumberLiteral('0'), $value, new NullLiteral()]),
            'case' => new DeferredCase(null, [new DeferredCaseBranch(new NumberLiteral('0'), $value)]),
            'cast' => new DeferredCast($value, new CastTarget(CastKind::Char)),
            'not' => new DeferredNot($value),
            'minus' => new DeferredUnary(UnaryOperator::Minus, $value),
            'null', 'not-null' => new DeferredNullTest($value, $form === 'not-null'),
            default => $value,
        };
        $body = new DeferredSelection([new DeferredProjection($value)]);
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($create);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        self::assertNotInstanceOf(SqlError::class, $result1);
        $code = $form === 'zone' ? 1298 : 3998;
        $message = $form === 'zone' ? "Unknown or incorrect time zone: '" . $zone . "'" : 'Cannot cast value to TIMESTAMP WITH TIME ZONE.';
        $conditions = [['Error', (string) $code, $message]];
        foreach ([0, 1, 0] as $run) {
            $statement2 = (new Embedding())->statement(new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]));
            $parameters2 = array_fill(0, (new Bindings())->count($statement2), [null, Domain::null()]);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            try {
                $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
                $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), $parameters2);
            } catch (SqlError $executionError) {
                $result2 = $executionError;
                (new Execution($session))->failed($executionError);
            }
            self::assertNotInstanceOf(SqlError::class, $result2);
            $statement3 = (new Embedding())->statement($call);
            $parameters3 = array_fill(0, (new Bindings())->count($statement3), [null, Domain::null()]);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            try {
                $operation3 = (new Binding())->operation($session, $statement3, $parameters3, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
                $result3 = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), $parameters3);
            } catch (SqlError $executionError) {
                $result3 = $executionError;
                (new Execution($session))->failed($executionError);
            }
            $warnings3 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
            $outcome = $result3;
            if ($run === 0) {
                self::assertNotInstanceOf(SqlError::class, $outcome);
                self::assertSame([], $warnings3);
            } else {
                self::assertSame([$code, 'HY000', $message], $outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null);
                self::assertSame($conditions, $warnings3);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
