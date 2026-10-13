<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(LegacyForms::class)]
#[Small]
final class LegacyFormsTest extends TestCase
{
    public function testRequiresDistinguishesRemovedOptionsFromRetainedOptions(): void
    {
        $forms = new LegacyForms();

        self::assertTrue($forms->requires(SelectOption::Cache));
        self::assertTrue($forms->requires(ExplainModifier::Partitions));
        self::assertFalse($forms->requires(SelectOption::NoCache));
    }
    public function testQueryDistinguishesLegacyOrderingFromPlainBlocks(): void
    {
        $body = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
        $ordered = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, $body, [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')))]);

        self::assertFalse((new LegacyForms())->query($body));
        self::assertTrue((new LegacyForms())->query($ordered));
    }

    public function testDefinitionRecognizesOnlyDefaultRoutineCollation(): void
    {
        $forms = new LegacyForms();
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char);
        $name = new \SqlSemantics\Statement\Identifier\Name('arg');
        $default = new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter($name, $type, new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null));
        $named = new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter($name, $type, new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('latin1_bin')));

        self::assertTrue($forms->definition($default));
        self::assertFalse($forms->definition($named));
        self::assertFalse($forms->definition($type));
    }
}
