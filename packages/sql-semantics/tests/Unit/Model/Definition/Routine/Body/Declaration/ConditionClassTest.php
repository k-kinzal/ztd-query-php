<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionClass;

#[CoversClass(ConditionClass::class)]
final class ConditionClassTest extends TestCase
{
    public function testSpellsEveryConditionClass(): void
    {
        self::assertSame(['SQLWARNING', 'NOT FOUND', 'SQLEXCEPTION'], array_column(ConditionClass::cases(), 'value'));
    }
}
