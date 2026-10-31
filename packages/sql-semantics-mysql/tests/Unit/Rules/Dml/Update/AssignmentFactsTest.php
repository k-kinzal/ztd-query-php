<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\Update\AssignmentFacts;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(AssignmentFacts::class)]
#[Small]
final class AssignmentFactsTest extends TestCase
{
    public function testDeriveResolvesEveryDestinationBeforeTheOrderedValues(): void
    {
        $first = new M\Routine\Program\Write\DeferredColumnAssignment(new M\Name\ColumnUse(new Name('a')), new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]));
        $second = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('b')), new M\Name\ColumnUse(new Name('value')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([], true));
        $fields = (new AssignmentFacts())->derive([$first, $second], $derivation, $derivation->environment());
        self::assertCount(2, $fields);
        self::assertSame([MissingColumn::class, MissingColumn::class, M\Expression\Problem\OperandColumns::class, MissingColumn::class], array_map(static fn ($problem): string => $problem::class, $derivation->facts()->diagnostics));
        self::assertTrue($first->value->hasBinding());
        self::assertTrue($second->value->hasBinding());
    }
}
