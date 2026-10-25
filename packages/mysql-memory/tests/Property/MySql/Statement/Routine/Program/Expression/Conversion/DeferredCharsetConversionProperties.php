<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Program\Expression\Conversion;

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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
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
final class DeferredCharsetConversionProperties extends TestCase
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
     * @return iterable<string, array{string, string, int<0, 9>}>
     */
    public static function providerCharsetLoadHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['utf8', 'utf8mb3', 'ucs2'] as $charset) {
                for ($seed = 0; $seed < 10; $seed++) {
                    yield $release . ':' . $charset . ':' . $seed => [$release, $charset, $seed];
                }
            }
        }
    }

    /**
     * Reuses the same call structure after loading, caching and invalidating its definition.
     *
     * @param int<0, 9> $seed The typed row generation seed
     */
    #[DataProvider('providerCharsetLoadHistories')]
    #[Test]
    public function satisfiesUnchangedInvocationKeepsTheVersionedCharsetHistory(string $release, string $charset, int $seed): void
    {
        $choices = new Choices('charset-load:' . $seed);
        $values = [];
        $width = $choices->integer(2, 4);
        for ($index = 0; $index < $width; $index++) {
            $values[] = new NumberLiteral((string) $choices->integer(0, 1000));
        }
        $operand = new Model(new Row($values), new CharsetName(new Name($charset)));
        $body = new DeferredSelection([new DeferredProjection($operand)]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([new ConditionalBranch(new UserVariable(new Name('run')), [$body])]));
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
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $defined = $result1;
        self::assertNotInstanceOf(SqlError::class, $defined);
        self::assertSame([$charset === 'utf8' ? '3719' : '1287'], array_column($warnings1, 1));
        foreach (['SET @run=0', 'SET @run=0', "ALTER PROCEDURE p COMMENT 'reload'", 'SET @run=1'] as $phase => $fixture) {
            $session->query($fixture);
            $session->text = 'unrelated invalid input';
            $statement2 = (new Embedding())->statement($call);
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
            $warnings2 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
            $outcome = $result2;
            self::assertSame($phase === 3 ? 1241 : null, ($outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null)[0] ?? null);
            self::assertSame($phase === 3 ? ['1241'] : ($charset === 'utf8' && $phase !== 1 ? ['3719'] : []), array_column($warnings2, 1));
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
