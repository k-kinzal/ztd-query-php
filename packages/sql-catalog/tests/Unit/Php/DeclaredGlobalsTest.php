<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PhpParser\Node\Stmt\Global_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;

#[CoversClass(DeclaredGlobals::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceParser::class)]
final class DeclaredGlobalsTest extends TestCase
{
    public function testClassOfNamesAGlobalAnExtensionDeclares(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function f() { global $wpdb; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $statement);
        $declaration = $statement->stmts[0];
        self::assertInstanceOf(Global_::class, $declaration);

        self::assertSame('wpdb', (new DeclaredGlobals(['wpdb' => 'wpdb']))->classOf($declaration, 'wpdb'));
    }

    public function testClassOfIsSilentWhenNothingSaysWhatTheGlobalHolds(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function f() { global $other; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $statement);
        $declaration = $statement->stmts[0];
        self::assertInstanceOf(Global_::class, $declaration);

        self::assertNull((new DeclaredGlobals())->classOf($declaration, 'other'));
    }

    public function testDeclaredNamesTheClassAnExtensionGivesAGlobal(): void
    {
        $globals = new DeclaredGlobals(['wpdb' => 'App\\Db', 'cache' => 'App\\Cache']);

        self::assertSame('App\\Db', $globals->declared('wpdb'));
        self::assertSame('App\\Cache', $globals->declared('cache'));
    }

    public function testDeclaredIsSilentForAGlobalNoExtensionDeclares(): void
    {
        self::assertNull((new DeclaredGlobals(['wpdb' => 'App\\Db']))->declared('other'));
        self::assertNull((new DeclaredGlobals(['wpdb' => 'App\\Db']))->declared('$wpdb'));
        self::assertNull((new DeclaredGlobals())->declared('wpdb'));
    }

    public function testDocumentedReadsTheTagOnTheEnclosingDeclaration(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php' . "\n" . '/** @global \\App\\Db $db */' . "\n" . 'function f() { global $db; }',
        );
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $statement);
        $declaration = $statement->stmts[0];
        self::assertInstanceOf(Global_::class, $declaration);

        self::assertSame('App\\Db', (new DeclaredGlobals())->documented($declaration, 'db'));
    }

    public function testDocumentedIsSilentWhenNoTagNamesTheVariable(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php' . "\n" . '/** @global wpdb $wpdb */' . "\n" . 'function f() { global $db; }',
        );
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $statement);
        $declaration = $statement->stmts[0];
        self::assertInstanceOf(Global_::class, $declaration);

        self::assertNull((new DeclaredGlobals())->documented($declaration, 'db'));
    }

    public function testCommentsClimbToTheDeclarationTheGlobalIsWrittenIn(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php' . "\n" . '/** @global wpdb $wpdb */' . "\n" . 'function f() { /** @var wpdb $wpdb */ global $wpdb; }',
        );
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $statement);
        $declaration = $statement->stmts[0];
        self::assertInstanceOf(Global_::class, $declaration);

        self::assertSame(
            ['/** @var wpdb $wpdb */', '/** @global wpdb $wpdb */'],
            (new DeclaredGlobals())->comments($declaration),
        );
    }

    /**
     * @return list<array{string, string, string|null}>
     */
    public static function providerTagged(): array
    {
        return [
            ['/** @global wpdb $wpdb */', 'wpdb', 'wpdb'],
            ['/** @var \\App\\Db $db */', 'db', 'App\\Db'],
            ['/** @global wpdb|null $wpdb */', 'wpdb', 'wpdb'],
            ['/** @global wpdb $wpdb */', 'other', null],
            ['/** nothing */', 'wpdb', null],
            ['/** @global wpdb $wpdbx */', 'wpdb', null],
        ];
    }

    #[DataProvider('providerTagged')]
    public function testTaggedReadsTheClassOutOfADocComment(string $comment, string $name, ?string $expected): void
    {
        self::assertSame($expected, (new DeclaredGlobals())->tagged($comment, $name));
    }
}
