<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;

#[CoversNothing]
final class BisonAlternativeNodeTest extends TestCase
{
    public function testConstructorAllValues(): void
    {
        $sym = new BisonSymbolNode(BisonSymbolType::Identifier, 'SELECT');
        $node = new BisonAlternativeNode([$sym], '{ $$ = $1; }', 'UMINUS', 1, '<merge>');

        self::assertSame([$sym], $node->symbols);
        self::assertSame('{ $$ = $1; }', $node->action);
        self::assertSame('UMINUS', $node->prec);
        self::assertSame(1, $node->dprec);
        self::assertSame('<merge>', $node->merge);
    }

    public function testConstructorWithNulls(): void
    {
        $node = new BisonAlternativeNode([], null, null, null, null);

        self::assertSame([], $node->symbols);
        self::assertNull($node->action);
        self::assertNull($node->prec);
        self::assertNull($node->dprec);
        self::assertNull($node->merge);
    }
}
