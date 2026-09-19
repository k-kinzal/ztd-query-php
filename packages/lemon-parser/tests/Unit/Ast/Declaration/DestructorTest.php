<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Destructor;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Destructor::class)]
#[UsesClass(Location::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class DestructorTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Destructor(new Symbol('expr', new Location(3, 13)), ' free($$); ', ArgumentForm::Code, new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('expr', $declaration->symbol->name);
        self::assertSame(' free($$); ', $declaration->value);
        self::assertSame(ArgumentForm::Code, $declaration->form);
    }
}
