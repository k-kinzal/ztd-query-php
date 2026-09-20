<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Define;
use BisonParser\Ast\Declaration\DefineForm;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Define::class)]
#[UsesClass(Location::class)]
#[UsesClass(DefineForm::class)]
#[Small]
final class DefineTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Define('api.pure', 'full', DefineForm::Keyword, new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('api.pure', $declaration->variable);
        self::assertSame('full', $declaration->value);
        self::assertSame(DefineForm::Keyword, $declaration->form);
    }
}
