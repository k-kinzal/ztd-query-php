<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Table\Column\Generation;

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
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TargetTable;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn as Model;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
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
final class DeferredGeneratedColumnProperties extends TestCase
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
     * @return iterable<string, array{string, int}>
     */
    public static function providerGeneratedGroupsHistories(): iterable
    {
        foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 10; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Constructs every operation directly and retries the unchanged procedure call.
     */
    #[DataProvider('providerGeneratedGroupsHistories')]
    #[Test]
    public function satisfiesAConditionalGenerationDefinitionRetainsItsOperandAcrossCatalogStates(string $release, int $seed): void
    {
        $choices = new Choices('generated-groups:' . $seed);
        $kinds = [AggregateFunction::Sum, AggregateFunction::Maximum, AggregateFunction::Average];
        $expression = new Aggregate($kinds[$choices->integer(0, 2)], [new ColumnUse(new Name('a'))]);
        $column = new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)));
        $generated = new ColumnDefinition(new ColumnName(new Name('g')), new Model(new Integral(IntegralKind::Int), $expression));
        $body = new DeferredCreateTable(new QualifiedName(new Name('t')), [$column, $generated], ifNotExists: true);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), $body);
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->text = 'opaque request label';
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
        $before = $result2;
        self::assertSame([1111, 'HY000', 'Invalid use of group function'], $before instanceof SqlError ? [$before->getCode(), $before->sqlState(), $before->getMessage()] : null);
        $statement3 = (new Embedding())->statement(new CreateTable(new QualifiedName(new Name('t')), [$column]));
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
        self::assertNotInstanceOf(SqlError::class, $result3);
        $insert = new InsertRows(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')))), [new InsertedRow([new NumberLiteral((string) $choices->integer(1, 1000))])]);
        $statement4 = (new Embedding())->statement($insert);
        $parameters4 = array_fill(0, (new Bindings())->count($statement4), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation4 = (new Binding())->operation($session, $statement4, $parameters4, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
            $result4 = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), $parameters4);
        } catch (SqlError $executionError) {
            $result4 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        self::assertNotInstanceOf(SqlError::class, $result4);
        $statement5 = (new Embedding())->statement($call);
        $parameters5 = array_fill(0, (new Bindings())->count($statement5), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation5 = (new Binding())->operation($session, $statement5, $parameters5, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
            $result5 = (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), $parameters5);
        } catch (SqlError $executionError) {
            $result5 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings5 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $present = $result5;
        self::assertNotInstanceOf(SqlError::class, $present);
        self::assertSame([['Note', '1050', "Table 't' already exists"]], $warnings5);
        $statement6 = (new Embedding())->statement(new DropTable(false, false, [new TargetTable(new QualifiedName(new Name('t')))]));
        $parameters6 = array_fill(0, (new Bindings())->count($statement6), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation6 = (new Binding())->operation($session, $statement6, $parameters6, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement6)));
            $result6 = (new Execution($session))->perform($operation6, (new Dispatcher())->command($operation6->statement), $parameters6);
        } catch (SqlError $executionError) {
            $result6 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        self::assertNotInstanceOf(SqlError::class, $result6);
        $statement7 = (new Embedding())->statement($call);
        $parameters7 = array_fill(0, (new Bindings())->count($statement7), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation7 = (new Binding())->operation($session, $statement7, $parameters7, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement7)));
            $result7 = (new Execution($session))->perform($operation7, (new Dispatcher())->command($operation7->statement), $parameters7);
        } catch (SqlError $executionError) {
            $result7 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        self::assertSame($before instanceof SqlError ? [$before->getCode(), $before->sqlState(), $before->getMessage()] : null, $result7 instanceof SqlError ? [$result7->getCode(), $result7->sqlState(), $result7->getMessage()] : null);
        self::assertSame($body, $create->body);
        self::assertInstanceOf(Model::class, $generated->specification);
        self::assertSame($expression, $generated->specification->expression);
        self::assertSame('opaque request label', $session->text);
    }
}
