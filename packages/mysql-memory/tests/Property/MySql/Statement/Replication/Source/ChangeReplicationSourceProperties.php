<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Replication\Source;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource as Model;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ChangeReplicationSourceProperties extends TestCase
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
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{SourceOption}>
     * @throws JsonException When the value inventory is invalid
     * @throws RuntimeException When the finite sample does not contain enough switches
     */
    public static function providerSourceSwitchesOptions(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $found = 0;
        for ($seed = 0; $seed < 10000 && $found < 100; $seed++) {
            $value = (new Construction(new Choices('source-switch:' . $seed), $catalogue, $metadata))->object(SourceOption::class, 4);
            if ($value instanceof SourceOption && in_array($value->kind, [SourceOptionKind::ConnectionAutoFailover, SourceOptionKind::GtidOnly, SourceOptionKind::RequireRowFormat], true)) {
                $found++;
                yield 'source-switch:' . $seed => [$value];
            }
        }
        if ($found !== 100) {
            throw new RuntimeException('The finite constructor sampling budget did not produce 100 source switches.');
        }
    }

    /**
     * Executes an accepted switch against a channel with GTIDs, auto-positioning and row events.
     */
    #[DataProvider('providerSourceSwitchesOptions')]
    #[Test]
    public function satisfiesGeneratedSwitchExecutesWithItsChannelPrerequisites(SourceOption $option): void
    {
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON']))->connect();
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement(new Model(Terminology::Current, [new SourceOption(Terminology::Current, SourceOptionKind::AutoPosition, new Numeral('1')), new SourceOption(Terminology::Current, SourceOptionKind::RequireRowFormat, new Numeral('1'))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new Model(Terminology::Current, [$option]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []));
        self::assertSame([], $session->diagnostics->conditions);
    }
}
