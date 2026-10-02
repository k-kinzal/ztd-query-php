<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ModuleArguments;

#[CoversClass(ModuleArguments::class)]
#[Medium]
final class ModuleArgumentsTest extends TestCase
{
    public function testTextsAreTheExactSpansOfTheArguments(): void
    {
        $tree = (new SqliteParser())->parse("CREATE VIRTUAL TABLE t USING m( a  b ,, /* c */ f( x , 'y,z' ) -- d\n )");

        self::assertSame(['a  b', '', "f( x , 'y,z' )"], (new ModuleArguments())->texts($tree->find('vtabarglist')[0]));
    }

    public function testTextsKeepCommentsWrittenBetweenTheTokensOfAnArgument(): void
    {
        $tree = (new SqliteParser())->parse('CREATE VIRTUAL TABLE t USING m(a /* int */ b)');

        self::assertSame(['a /* int */ b'], (new ModuleArguments())->texts($tree->find('vtabarglist')[0]));
    }

    public function testTokensAreInTextOrder(): void
    {
        $tree = (new SqliteParser())->parse('CREATE VIRTUAL TABLE t USING m(a (b c) d)');
        $texts = array_map(static fn (object $token): string => $token->text, (new ModuleArguments())->tokens($tree->find('vtabarglist')[0]));

        self::assertSame(['a', '(', 'b', 'c', ')', 'd'], $texts);
    }

    public function testSingleAcceptsOneArgumentText(): void
    {
        $arguments = new ModuleArguments();

        self::assertTrue($arguments->single(''));
        self::assertTrue($arguments->single('content'));
        self::assertTrue($arguments->single("tokenize = 'porter, ascii'"));
        self::assertTrue($arguments->single('f(a, b)  c'));
    }

    public function testSingleRefusesTextSqliteWouldReadDifferently(): void
    {
        $arguments = new ModuleArguments();

        self::assertFalse($arguments->single('a, b'));
        self::assertFalse($arguments->single(' a'));
        self::assertFalse($arguments->single('a '));
        self::assertFalse($arguments->single('a)'));
        self::assertFalse($arguments->single('(a'));
        self::assertFalse($arguments->single("'open"));
        self::assertFalse($arguments->single('a -- b'));
        self::assertFalse($arguments->single('a); DROP TABLE t; --'));
    }
}
