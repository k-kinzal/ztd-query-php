<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Php\SourceParser;

#[CoversClass(ModifiedNames::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Objects\ObjectEffects::class)]
final class ModifiedNamesTest extends TestCase
{
    /**
     * @return list<array{string, list<string>}>
     */
    public static function providerOf(): array
    {
        return [
            ['$sql = "SELECT 1";', ['sql']],
            ['$sql .= " WHERE 1";', ['sql']],
            ['$rows["a"][] = 1;', ['rows']],
            ['$this->request = $sql;', ['this->request']],
            ['[$a, $b] = $pair;', ['a', 'b']],
            ['$i++;', ['i']],
            ['if ($x) { $a = 1; } else { $b = 2; }', ['a', 'b']],
            ['foreach ($rows as $key => $row) { $n = $row; }', ['row', 'key', 'n']],
            ['global $wpdb;', ['wpdb']],
            ['static $cache = [];', ['cache']],
            ['unset($sql);', ['sql']],
            ['try { } catch (Exception $e) { }', ['e']],
            ['$f = function () { $inner = 1; };', ['f']],
            ['function g() { $inside = 1; }', []],
            ['echo $sql;', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerOf')]
    public function testOfNamesEverythingAStatementMayAssign(string $code, array $expected): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php ' . $code)->statements[0];

        self::assertSame($expected, array_keys((new ModifiedNames())->of($statement)));
    }

    public function testTouchesSaysWhetherAStatementAssignsAnyWantedName(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php $sql = 1;')->statements[0];
        $modified = new ModifiedNames();

        self::assertTrue($modified->touches($statement, ['sql' => true]));
        self::assertFalse($modified->touches($statement, ['other' => true]));
        self::assertFalse($modified->touches($statement, []));
    }

    public function testCollectWorksTheAnswerOutFromScratch(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php $a = $b = 1;')->statements[0];

        self::assertSame(['a', 'b'], array_keys((new ModifiedNames())->collect($statement)));
    }

    public function testOwnLeavesOutWhatTheChildrenAssign(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php if ($x) { $a = 1; }')->statements[0];
        self::assertInstanceOf(Stmt\If_::class, $statement);

        self::assertSame([], (new ModifiedNames())->own($statement));
    }

    public function testTargetsNameEveryVariableADestructuringWrites(): void
    {
        $target = new Expr\List_([
            new \PhpParser\Node\ArrayItem(new Expr\Variable('a')),
            null,
            new \PhpParser\Node\ArrayItem(new Expr\Variable('b')),
        ]);

        self::assertSame(['a' => true, 'b' => true], (new ModifiedNames())->targets($target));
    }

    public function testBaseNameLooksThroughElementWrites(): void
    {
        $modified = new ModifiedNames();

        self::assertSame('rows', $modified->baseName(new Expr\ArrayDimFetch(new Expr\ArrayDimFetch(new Expr\Variable('rows')))));
        self::assertSame('this->parts', $modified->baseName(new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'parts'))));
        self::assertNull($modified->baseName(new Expr\Variable(new Expr\Variable('name'))));
    }

    public function testAllNamesWhatEachTargetWrites(): void
    {
        self::assertSame(['a' => true, 'b' => true], (new ModifiedNames())->all([new Expr\Variable('a'), new Expr\Variable('b')]));
    }

    public function testTracksObjectsRequiresAnExplicitEffectModel(): void
    {
        self::assertFalse((new ModifiedNames())->tracksObjects());
        self::assertTrue((new ModifiedNames(objects: new \SqlCatalog\Analysis\Derivation\Objects\ObjectEffects()))->tracksObjects());
    }
}
