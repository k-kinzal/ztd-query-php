<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use LemonParser\Ast\Location;
use LemonParser\Syntax\SymbolRegistry;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolRegistry::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[Small]
final class SymbolRegistryTest extends TestCase
{
    public function testSee(): void
    {
        $registry = new SymbolRegistry();

        $registry->see('expr');

        self::assertTrue($registry->isKnown('expr'));
    }

    public function testIsKnown(): void
    {
        $registry = new SymbolRegistry();

        self::assertFalse($registry->isKnown('expr'));
        $registry->rank('PLUS', new Location(1, 1));
        self::assertTrue($registry->isKnown('PLUS'));
    }

    public function testRank(): void
    {
        $registry = new SymbolRegistry();
        $registry->rank('PLUS', new Location(1, 7));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Symbol "PLUS" has already be given a precedence. at 2:8');

        $registry->rank('PLUS', new Location(2, 8));
    }

    public function testType(): void
    {
        $registry = new SymbolRegistry();
        $registry->type('expr', new Location(1, 7));

        self::assertTrue($registry->isKnown('expr'));
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Symbol %type "expr" already defined at 2:7');

        $registry->type('expr', new Location(2, 7));
    }

    public function testFallBack(): void
    {
        $registry = new SymbolRegistry();
        $registry->fallBack('ABORT', new Location(1, 14));

        self::assertTrue($registry->isKnown('ABORT'));
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('More than one fallback assigned to token ABORT at 2:14');

        $registry->fallBack('ABORT', new Location(2, 14));
    }

    public function testWildcard(): void
    {
        $registry = new SymbolRegistry();
        $registry->wildcard('ANY', new Location(1, 11));

        self::assertTrue($registry->isKnown('ANY'));
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Extra wildcard to token: OTHER at 1:15');

        $registry->wildcard('OTHER', new Location(1, 15));
    }
}
