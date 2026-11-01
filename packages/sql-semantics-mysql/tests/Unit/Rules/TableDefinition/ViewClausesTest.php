<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewClauses;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ViewClauses::class)]
#[Small]
final class ViewClausesTest extends TestCase
{
    public function testBeforeKeepsModifiersAndIgnoresTheDestinationVariable(): void
    {
        $into = new IntoVariables([new UserVariable(new Name('target'))]);
        $query = new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))], into: $into, intoPosition: IntoPosition::AfterItems);
        $prefix = (new ViewClauses())->before($query, GrammarRelease::MySql5744);

        self::assertNotNull($prefix);
        self::assertFalse($prefix[0]);
        self::assertCount(1, $prefix[1]);
    }

    public function testNodesVisitsTheFirstVariableBeforeItsDestination(): void
    {
        $read = new UserVariable(new Name('read'));
        $into = new IntoVariables([new UserVariable(new Name('write'))]);
        $item = new SelectExpression($read);
        $query = new Select([], [$item], into: $into, intoPosition: IntoPosition::AfterItems);
        $nodes = iterator_to_array((new ViewClauses())->nodes($query), false);

        self::assertSame([$query, $item, $read], array_slice($nodes, 0, 3));
        $prefix = (new ViewClauses())->before($query, GrammarRelease::MySql5651);
        self::assertNotNull($prefix);
        self::assertTrue($prefix[0]);
        self::assertNull((new ViewClauses())->before(new Select([], [new SelectExpression(new NumberLiteral('1'))]), GrammarRelease::MySql5651));
    }

    public function testSelectPlacesIntoAfterItsPrecedingFilter(): void
    {
        $where = new UserVariable(new Name('predicate'));
        $into = new IntoVariables([new UserVariable(new Name('write'))]);
        $item = new SelectExpression(new NumberLiteral('1'));
        $query = new Select([], [$item], where: $where, into: $into, intoPosition: IntoPosition::AfterQuery);
        $clauses = array_values(array_filter((new ViewClauses())->select($query)));

        self::assertSame([$item, $where, $into], $clauses);
        $prefix = (new ViewClauses())->before($query, GrammarRelease::MySql5744);
        self::assertNotNull($prefix);
        self::assertTrue($prefix[0]);
    }
}
