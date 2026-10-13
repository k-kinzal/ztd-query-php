<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
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

/**
 * Checks parameter binding from directly constructed trees with nested scopes.
 */
#[CoversNothing]
#[Medium]
final class ParameterTest extends TestCase
{
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
    public function testInputsBindInWrittenOrderRatherThanDerivationOrder(int $projected, int $nested): void
    {
        $query = new Select([], [new SelectExpression(new Parameter())], new DerivedTable(new Select([], [new SelectExpression(new Parameter(), new Name('a'))]), new Name('t')), new Parameter());
        $reply = (new Execution())->run($query, parameters: [[$projected, Domain::integer()], [$nested, Domain::integer()], [1, Domain::integer()]]);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) $projected]], $reply->rows);
    }
}
