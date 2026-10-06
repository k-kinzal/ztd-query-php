<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\TypeDescriptor;

#[CoversClass(Choice::class)]
#[Medium]
final class ChoiceTest extends TestCase
{
    public function testAlternativesAreTheKnownTypesInRuleOrder(): void
    {
        $choice = new Choice([Storage::Integer, Storage::Real]);

        self::assertSame([Storage::Integer, Storage::Real], $choice->alternatives);
    }

    public function testAlternativesOfAnExpressionTheRulesCannotDecideStatically(): void
    {
        $type = (new Semantics(Dialect::Sqlite))->analyze("SELECT CASE WHEN 1 THEN 1 ELSE 'a' END")->field(0)->type;

        self::assertInstanceOf(Choice::class, $type);
        self::assertSame(['INTEGER', 'TEXT'], array_map(static fn (TypeDescriptor $descriptor): string => $descriptor->name(), $type->alternatives));
    }

    public function testAlternativesAreAtLeastTwo(): void
    {
        $this->expectExceptionMessage('A type choice holds at least two type descriptors.');

        new Choice([Storage::Integer]);
    }
}
