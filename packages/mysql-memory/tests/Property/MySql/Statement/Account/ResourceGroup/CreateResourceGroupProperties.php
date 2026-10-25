<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account\ResourceGroup;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
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
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup as Model;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class CreateResourceGroupProperties extends TestCase
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
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerResourceGroupHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 60; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Builds fresh operands for creation, alteration, assignment and removal without parsing SQL.
     */
    #[DataProvider('providerResourceGroupHistories')]
    #[Test]
    public function satisfiesNamesRetainTheirIdentityAcrossCommands(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-name:' . $seed);
        $character = $choices->one(['a', '猫', 'é', '😀', ' ', '`', "\t"]);
        $width = strlen(ResourceGroupName::decoded(new Name($character)));
        $text = str_repeat($character, $choices->integer(0, intdiv(64, $width)));
        $name = new ResourceGroupName(new Name($text));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement(new Model($name, ResourceGroupKind::User));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new AlterResourceGroup(new ResourceGroupName(new Name($name->value))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        $statement3 = (new Embedding())->statement(new SetResourceGroup(new ResourceGroupName(new Name($text))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertNotNull($session->instance->registry->resourceGroups->find($name->value));
        self::assertTrue($session->instance->registry->resourceGroups->busy($name->value));
        $statement4 = (new Embedding())->statement(new DropResourceGroup(new ResourceGroupName(new Name($name->value)), true));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation4 = (new Binding())->operation($session, $statement4, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
        self::assertNull($session->instance->registry->resourceGroups->find($name->value));
    }

    /**
     * The same typed CPU range fails or succeeds according to explicit platform state.
     */
    #[DataProvider('providerResourceGroupHistories')]
    #[Test]
    public function satisfiesCpuRangesExecuteWhenTheirProcessorsExist(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-cpus:' . $seed);
        $first = $choices->integer(8, 15);
        $last = $choices->integer($first, 31);
        $statement = new Model(new ResourceGroupName(new Name('g')), ResourceGroupKind::User, [new CpuRange($first, $last)]);
        $small = (new Instance($release, resourceProcessors: 8))->connect();
        $large = (new Instance($release, resourceProcessors: 32))->connect();
        $small->text = $large->text = 'unrelated invalid SQL';
        $failure = null;
        try {
            $statement5 = (new Embedding())->statement($statement);
            $small->activity->begin();
            StatementCounters::received($small);
            $small->hinted = [];
            $small->dots = [];
            $operation5 = (new Binding())->operation($small, $statement5, [], new LanguageProfile($small->semantics()->profile()->grammar, $small->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
            (new Execution($small))->perform($operation5, (new Dispatcher())->command($operation5->statement), []);
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(3652, $failure->getCode());
        self::assertSame('Invalid cpu id ' . $first, $failure->getMessage());
        $statement6 = (new Embedding())->statement($statement);
        $large->activity->begin();
        StatementCounters::received($large);
        $large->hinted = [];
        $large->dots = [];
        $operation6 = (new Binding())->operation($large, $statement6, [], new LanguageProfile($large->semantics()->profile()->grammar, $large->semantics()->profile()->lexical, (new Bindings())->parameters($statement6)));
        self::assertInstanceOf(Completion::class, (new Execution($large))->perform($operation6, (new Dispatcher())->command($operation6->statement), []));
        self::assertSame(range($first, $last), $large->instance->registry->resourceGroups->find('g')?->cpus);
    }

    /**
     * Creation and alteration consume the same signed value without reading SQL.
     */
    #[DataProvider('providerResourceGroupHistories')]
    #[Test]
    public function satisfiesPrioritiesRetainTheirValuesAcrossCommands(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-priority:' . $seed);
        $kind = $choices->one(ResourceGroupKind::cases());
        $created = $kind === ResourceGroupKind::System ? $choices->integer(-20, 0) : $choices->integer(0, 19);
        $changed = $kind === ResourceGroupKind::System ? $choices->integer(-20, 0) : $choices->integer(0, 19);
        $zeros = str_repeat('0', $choices->integer(0, 8));
        $negativeZero = $choices->one([true, false]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement7 = (new Embedding())->statement(new Model(new ResourceGroupName(new Name('g')), $kind, priority: new ThreadPriority($created < 0 || $created === 0 && $negativeZero, new Numeral($zeros . abs($created)))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation7 = (new Binding())->operation($session, $statement7, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement7)));
        (new Execution($session))->perform($operation7, (new Dispatcher())->command($operation7->statement), []);
        $group = $session->instance->registry->resourceGroups->find('g');
        self::assertNotNull($group);
        self::assertSame($created, $group->priority);
        self::assertSame($kind === ResourceGroupKind::System, $group->system);
        $statement8 = (new Embedding())->statement(new AlterResourceGroup(new ResourceGroupName(new Name('g')), priority: new ThreadPriority($changed < 0 || $changed === 0 && $negativeZero, new Numeral($zeros . abs($changed)))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation8 = (new Binding())->operation($session, $statement8, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement8)));
        (new Execution($session))->perform($operation8, (new Dispatcher())->command($operation8->statement), []);
        self::assertSame($changed, $group->priority);
    }

    /**
     * The same priority domain is executable when the platform ignores priorities.
     */
    #[DataProvider('providerResourceGroupHistories')]
    #[Test]
    public function satisfiesPlatformWithoutPrioritiesAcceptsEveryGeneratedInteger(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-platform:' . $seed);
        $kind = $choices->one(ResourceGroupKind::cases());
        $number = $choices->one([0, 1, 19, 20, 21, 2147483647, $choices->integer(0, 2147483647)]);
        $negative = $choices->one([true, false]);
        $session = (new Instance($release, resourcePriorities: false))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement9 = (new Embedding())->statement(new Model(new ResourceGroupName(new Name('g')), $kind, priority: new ThreadPriority($negative, new Numeral((string) $number))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation9 = (new Binding())->operation($session, $statement9, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement9)));
        (new Execution($session))->perform($operation9, (new Dispatcher())->command($operation9->statement), []);
        $group = $session->instance->registry->resourceGroups->find('g');
        self::assertNotNull($group);
        self::assertSame(0, $group->priority);
        self::assertSame($number === 0 ? 0 : 1, $session->diagnostics->count());
        $statement10 = (new Embedding())->statement(new AlterResourceGroup(new ResourceGroupName(new Name('g')), priority: new ThreadPriority($negative, new Numeral('000' . $number))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation10 = (new Binding())->operation($session, $statement10, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement10)));
        (new Execution($session))->perform($operation10, (new Dispatcher())->command($operation10->statement), []);
        self::assertSame(0, $group->priority);
        self::assertSame($number === 0 ? 0 : 1, $session->diagnostics->count());
    }
}
