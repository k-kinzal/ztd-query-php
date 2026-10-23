<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;

/**
 * Checks parameter binding from directly constructed trees with nested scopes.
 */
#[CoversNothing]
#[Medium]
final class ParameterProperties extends TestCase
{
    /**
     * Direct construction consumes bindings without imposing wire-protocol preparation restrictions.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesDirectExecutionBindsValuesWithoutRequiringPreparedProtocolSupport(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(new Parameter());
        $parameters = [['2024-01-01 00:00:00', Domain::string(19, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())]];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, (new Execution())->run($node, parameters: $parameters));
    }

    /**
     * A native comparison retains the preparation checks of the selected wire protocol.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesNativeComparisonStillChecksPreparedProtocolSupport(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(new Parameter());
        $parameters = [['2024-01-01 00:00:00', Domain::string(19, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())]];
        $this->expectExceptionCode(1295);
        (new Execution())->runIn((new \MySqlMemory\Instance())->connect(), $node, $parameters);
    }

    /**
     * Resolves parent result types from explicit NULL bindings instead of unbound placeholders.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesNullFixtureResolvesParametersInsideTypedParentExpressions(): void
    {
        $query = new Select([], [
            new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Call\Temporal\GetFormat(\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat::Date, new Parameter(':format'))),
            new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation(new Parameter(), new Parameter())),
            new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression(null, [new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new Parameter())])),
        ]);
        $bindings = (new Bindings())->nulls($query);
        $reply = (new Execution())->run($query, parameters: $bindings, mode: 'PIPES_AS_CONCAT');

        self::assertCount(4, $bindings);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[null, null, null]], $reply->rows);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function providerBindings(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            $choices = new Choices('binding:' . $seed);
            yield (string) $seed => [$choices->integer(-1000, 1000), $choices->integer(-1000, 1000)];
        }
    }

    /**
     * A derived table is resolved before the projection but reads the second input slot.
     */
    #[DataProvider('providerBindings')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesInputsBindInWrittenOrderRatherThanDerivationOrder(int $projected, int $nested): void
    {
        $query = new Select([], [new SelectExpression(new Parameter())], new DerivedTable(new Select([], [new SelectExpression(new Parameter(), new Name('a'))]), new Name('t')), new Parameter());
        $reply = (new Execution())->run($query, parameters: [[$projected, Domain::integer()], [$nested, Domain::integer()], [1, Domain::integer()]]);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) $projected]], $reply->rows);
    }
    /**
     * The named extension preserves occurrence order alongside positional markers.
     */
    #[DataProvider('providerBindings')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesNamedAndPositionalInputsBindAcrossNestedScopes(int $projected, int $nested): void
    {
        $query = new Select([], [new SelectExpression(new Parameter(':projected'))], new DerivedTable(new Select([], [new SelectExpression(new Parameter(':nested'), new Name('a'))]), new Name('t')), new Parameter());
        $reply = (new Execution())->run($query, parameters: [[$projected, Domain::integer()], [$nested, Domain::integer()], [1, Domain::integer()]]);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) $projected]], $reply->rows);
    }
}
