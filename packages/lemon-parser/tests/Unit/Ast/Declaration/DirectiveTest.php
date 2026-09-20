<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\DirectiveKeyword;
use LemonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Directive::class)]
#[UsesClass(Location::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(DirectiveKeyword::class)]
#[Small]
final class DirectiveTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Directive(DirectiveKeyword::ExtraArgument, 'Parse *pParse', ArgumentForm::Code, new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(DirectiveKeyword::ExtraArgument, $declaration->keyword);
        self::assertSame('Parse *pParse', $declaration->value);
        self::assertSame(ArgumentForm::Code, $declaration->form);
    }
}
