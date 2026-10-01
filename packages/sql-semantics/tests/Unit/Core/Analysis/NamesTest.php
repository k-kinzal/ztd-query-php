<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\Names::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NamesTest extends TestCase
{
    public function testNameDecodesQuotedPunctuationAsOneIdentifier(): void
    {
        $names = new \SqlSemantics\Core\Analysis\Names(new \SqlSemantics\Core\Ast\Identifiers(Dialect::Sqlite));
        self::assertSame('a.b', $names->name(new \SqlParser\Lexer\Token(1, 'ID', '"a.b"', 0))->value);
    }

    public function testPartsPreservesQualificationBoundaries(): void
    {
        $parser = new \SqlSemantics\Core\Ast\DialectParser(Dialect::Sqlite);
        $expr = $parser->parse('SELECT a.foo')->find('expr')[0];
        $names = new \SqlSemantics\Core\Analysis\Names(new \SqlSemantics\Core\Ast\Identifiers(Dialect::Sqlite));
        self::assertSame(['a', 'foo'], array_column($names->parts($expr), 'value'));
    }
    public function testQualifiedRecognizesSeparatedIdentifierParts(): void
    {
        $dialect = Dialect::Sqlite;
        $tree = $dialect->platform()->parser()->parse('SELECT a.foo');
        $names = new \SqlSemantics\Core\Analysis\Names(new \SqlSemantics\Core\Ast\Identifiers($dialect));
        $syntax = $dialect->platform()->syntax();
        self::assertFalse($names->qualified($tree->children, $syntax));
        self::assertTrue($names->qualified($tree->find('expr')[0]->children, $syntax));
    }
}
