<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Call\Aggregate;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate as Model;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class AggregateProperties extends TestCase
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
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
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
     * @return iterable<string, array{string, bool, bool, int, int}>
     */
    public static function providerSpatialAggregatePoints(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                $choices = new Choices('spatial:' . $seed);
                yield $release . ':' . $seed => [$release, $choices->one([false, true]), $choices->one([false, true]), $choices->integer(-100, 100), $choices->integer(-100, 100)];
            }
        }
    }

    /**
     * Duplicate inputs and NULLs retain the same aggregate value in grouped and windowed forms.
     */
    #[DataProvider('providerSpatialAggregatePoints')]
    #[Test]
    public function satisfiesGeneratedCoordinatesRetainMultiplicityAndGeometryMetadata(string $release, bool $distinct, bool $window, int $x, int $y): void
    {
        $query = new Select([], [new SelectExpression(new Model(AggregateFunction::Collect, [new ColumnUse(new Name('column_0'))], $distinct, over: $window ? new WindowSpec() : null))], new DerivedTable(new ValuesQuery([new ValueRow([new Parameter()]), new ValueRow([new NullLiteral()]), new ValueRow([new Parameter()])]), new Name('input')));
        $point = Wkb::write(new Geometry(1, [[(float) $x, (float) $y]]));
        $session1 = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement($query);
        $parameters1 = [[$point, Domain::string(strlen($point), Collation::binary())], [$point, Domain::string(strlen($point), Collation::binary())]];
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, $parameters1, new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), $parameters1);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([$window ? Field::Blob : Field::Geometry, 16777216], [$reply->columns[0]->type, $reply->columns[0]->length]);
        self::assertCount($window ? 3 : 1, $reply->rows);
        self::assertNotNull($reply->rows[0][0]);
        $geometry = Wkb::read($reply->rows[0][0]);
        self::assertNotNull($geometry);
        self::assertSame(4, $geometry->type);
        self::assertSame(array_fill(0, $distinct ? 1 : 2, [[(float) $x, (float) $y]]), array_map(static fn (Geometry $member): array => $member->points, $geometry->children));
        self::assertSame(array_fill(0, $window ? 3 : 1, $reply->rows[0]), $reply->rows);
    }

    /**
     * The unrestricted constructor campaign's original counterexample has a complete result type.
     */
    #[Test]
    public function satisfiesNullSpatialAggregateExecutesWithoutAnUnresolvedType(): void
    {
        $session2 = (new Instance('8.4.7'))->connect();
        $statement2 = (new Embedding())->statement(new Model(AggregateFunction::Collect, [new NullLiteral()]));
        $session2->activity->begin();
        StatementCounters::received($session2);
        $session2->hinted = [];
        $session2->dots = [];
        $operation2 = (new Binding())->operation($session2, $statement2, [], new LanguageProfile($session2->semantics()->profile()->grammar, $session2->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session2))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[null]], $reply->rows);
        self::assertSame([Field::Geometry, 16777216, 31], [$reply->columns[0]->type, $reply->columns[0]->length, $reply->columns[0]->decimals]);
    }
}
