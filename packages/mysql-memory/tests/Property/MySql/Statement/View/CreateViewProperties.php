<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\View;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Parse\Definition\ViewInput;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as Model;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class CreateViewProperties extends TestCase
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
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerViewQueryNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                foreach (['table', 'column', 'function'] as $kind) {
                    yield $release . ':' . $seed . ':' . $kind => [$release, 'absent_' . (new Choices('view-query:' . $seed))->integer(0, 100000), $kind];
                }
            }
        }
    }

    /**
     * Resolves a generated absent relation, column or routine without consulting source text.
     */
    #[DataProvider('providerViewQueryNames')]
    #[Test]
    public function satisfiesQueryDiagnosticsNeedNoSourceText(string $release, string $name, string $kind): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        if ($kind !== 'table') {
            $initial = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
            $statement1 = (new Embedding())->statement(new Model(new ViewDefinition(new QualifiedName(new Name('missing_view')), $initial)));
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        }
        $session->text = 'unrelated invalid input';
        $query = match ($kind) {
            'table' => new Select([], [new Star()], new TableReference(new QualifiedName(new Name($name)))),
            'column' => new Select([], [new SelectExpression(new ColumnUse(new Name($name)))]),
            default => new Select([], [new SelectExpression(new FunctionCall(new Name($name)))]),
        };
        $statement = new AlterView(new ViewDefinition(new QualifiedName(new Name('missing_view'), new Name($kind === 'table' ? 'absent_schema' : 'd')), $query));
        $this->expectExceptionCode(match ($kind) {
            'table' => 1146,
            'column' => 1054,
            default => 1305,
        });
        $this->expectExceptionMessage(match ($kind) {
            'table' => "Table 'd.{$name}' doesn't exist",
            'column' => "Unknown column '{$name}' in 'field list'",
            default => "FUNCTION d.{$name} does not exist",
        });
        $statement2 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
    }

    /**
     * Executes a generated literal through a directly constructed view and checks its exposed type.
     */
    #[DataProvider('providerViewQueryNames')]
    #[Test]
    public function satisfiesMaterializedViewExposesItsResolvedIntegerType(string $release, string $name, string $kind): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $value = (string) (new Choices($name . ':' . $kind))->integer(0, 100000);
        $query = new Select([], [new SelectExpression(new NumberLiteral($value), new Name('a'))]);
        $statement3 = (new Embedding())->statement(new Model(new ViewDefinition(new QualifiedName(new Name('v')), $query)));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        $statement4 = (new Embedding())->statement(new Select([], [new Star()], new TableReference(new QualifiedName(new Name('v')))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation4 = (new Binding())->operation($session, $statement4, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        $result = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[$value]], $result->rows);
        self::assertSame(Field::Long, $result->columns[0]->type);
    }

    /**
     * An intrinsic view restriction is excluded from ordinary construction and retains typed diagnostic order.
     */
    #[DataProvider('providerViewQueryNames')]
    #[Test]
    public function satisfiesForbiddenBodiesCannotBecomeOrdinaryViews(string $release, string $name, string $kind): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $before = $kind === 'column';
        $read = new UserVariable(new Name($name));
        $item = new SelectExpression($before ? $read : new NumberLiteral('1'));
        $into = new IntoVariables([new UserVariable(new Name('target_' . $name))]);
        $query = new Select([], [$item], from: new Dual(), where: $before ? null : $read, into: $into, intoPosition: IntoPosition::AfterItems);
        $problem = new RefusedBody(new QualifiedName(new Name('v')), false, $query);
        $error = (new ViewInput())->error($problem, $session);
        self::assertSame($before ? 1351 : 1350, $error->getCode());
        self::assertSame($before ? "View's SELECT contains a variable or parameter" : "View's SELECT contains a 'INTO' clause", $error->getMessage());
        self::assertSame('unrelated invalid input', $session->text);
        $this->expectExceptionMessage($problem->message());
        new ViewDefinition(new QualifiedName(new Name('v')), $query);
    }
}
