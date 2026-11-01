<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;

#[CoversClass(Definition::class)]
#[Small]
final class DefinitionTest extends TestCase
{
    public function testSelectReturnsTheRequestedAlternativeFromACompleteDefinition(): void
    {
        $profiles = Interpretation::all(GrammarRelease::MySql847, ParameterStyle::Native);
        $statement = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $alternatives = array_map(static fn (Interpretation $profile): Alternative => new Alternative($profile, $statement), $profiles);
        $definition = new Definition($profiles[0], null, $alternatives);
        self::assertSame($alternatives[0], $definition->select($profiles[0]));
        self::assertSame($alternatives[1183], $definition->select($profiles[1183]));
    }

}
