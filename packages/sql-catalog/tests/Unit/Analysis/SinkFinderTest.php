<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\SinkFinder;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;

#[CoversClass(SinkFinder::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceParser::class)]
final class SinkFinderTest extends TestCase
{
    public function testFindInLooksAtTheNodesItIsGiven(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $finder = new SinkFinder();

        self::assertCount(1, $finder->findIn($file->statements, ['query' => true]));
        self::assertSame([], $finder->findIn($file->statements, ['prepare' => true]));
    }

    public function testReachingKeepsOnlyTheBodiesThatCanReachADatabaseCall(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function issues(PDO $d): void { $d->query("SELECT 1"); }'
            . ' function leadsThere(PDO $d): void { issues($d); }'
            . ' function unrelated(): int { return 1; }',
        );
        $finder = new SinkFinder();
        $reaching = $finder->reaching([$file], (new PdoExtension())->sinks());

        $named = array_map(
            static fn (?\PhpParser\Node\FunctionLike $body): string => $finder->declaredName($body) ?? 'main',
            array_keys(array_diff_key($finder->bodiesOf($file), $reaching)) === []
                ? []
                : array_intersect_key($finder->bodiesOf($file), $reaching),
        );
        sort($named);

        self::assertSame(['issues', 'leadsthere'], $named);
    }

    public function testReachingKeepsTopLevelCodeThatIssuesAStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $d = new PDO("sqlite::memory:"); $d->query("SELECT 1");');

        self::assertArrayHasKey('t.php:main', (new SinkFinder())->reaching([$file], (new PdoExtension())->sinks()));
    }

    public function testBodiesOfListsEveryBodyAndTheFileItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(): void {} class C { public function g(): void {} }');

        self::assertCount(3, (new SinkFinder())->bodiesOf($file));
    }

    public function testBodyKeyOfNamesTheBodyANodeIsWrittenIn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); } $d->query("SELECT 2");');
        $finder = new SinkFinder();
        $calls = $finder->find($file, (new PdoExtension())->sinks());

        self::assertNotSame('t.php:main', $finder->bodyKeyOf($file, $calls[0]));
        self::assertSame('t.php:main', $finder->bodyKeyOf($file, $calls[1]));
    }

    public function testDeclaredNameReadsTheNameABodyIsDeclaredUnder(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(): void {} $c = static function (): void {};');
        $finder = new SinkFinder();
        $bodies = array_values($finder->bodiesOf($file));

        self::assertNull($finder->declaredName($bodies[0]));
        self::assertSame('f', $finder->declaredName($bodies[1]));
        self::assertNull($finder->declaredName($bodies[2]));
    }

    public function testShortNameDropsTheNamespace(): void
    {
        $finder = new SinkFinder();

        self::assertSame('query', $finder->shortName('App\\Db\\query'));
        self::assertSame('query', $finder->shortName('\\query'));
        self::assertSame('query', $finder->shortName('query'));
    }

    public function testPropagateSpreadsReachingThroughTheCalls(): void
    {
        $bodies = [
            'a' => ['direct' => true, 'calls' => [], 'declares' => 'issues'],
            'b' => ['direct' => false, 'calls' => ['issues'], 'declares' => 'leads'],
            'c' => ['direct' => false, 'calls' => ['leads'], 'declares' => 'outer'],
            'd' => ['direct' => false, 'calls' => ['elsewhere'], 'declares' => 'apart'],
        ];

        self::assertSame(['a' => true, 'b' => true, 'c' => true], (new SinkFinder())->propagate($bodies));
    }

    public function testFindCollectsTheCallsWrittenLikeDatabaseCalls(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $d->query("SELECT 1"); $d->prepare("SELECT 2"); $d->fetchAll(); }',
        );

        self::assertCount(2, (new SinkFinder())->find($file, (new PdoExtension())->sinks()));
    }

    public function testFindIgnoresACallWrittenWithoutAName(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d, string $m): void { $d->$m("SELECT 1"); }');

        self::assertSame([], (new SinkFinder())->find($file, (new PdoExtension())->sinks()));
    }

    public function testNamesOfKeepsOnlyTheCallsThatCarryAStatement(): void
    {
        $names = (new SinkFinder())->namesOf((new PdoExtension())->sinks());

        self::assertArrayHasKey('query', $names);
        self::assertArrayHasKey('prepare', $names);
        self::assertArrayNotHasKey('bindvalue', $names);
        self::assertArrayNotHasKey('execute', $names);
    }

    public function testNameOfReadsEveryFormOfCall(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $d->query("a"); $d?->query("b"); PDO::query("c"); mysqli_query($d, "e"); new PDO("f"); }',
        );
        $finder = new SinkFinder();
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\CallLike::class);

        $names = array_map(
            static fn (Expr\CallLike $call): ?string => $finder->nameOf($call),
            $calls,
        );

        self::assertSame(['query', 'query', 'query', 'mysqli_query', null], $names);
    }

    public function testNameOfIgnoresAStaticCallWrittenWithoutAName(): void
    {
        $call = new Expr\StaticCall(new Name('PDO'), new Expr\Variable('m'));

        self::assertNull((new SinkFinder())->nameOf($call));
    }

    public function testEnclosingBodyFindsTheFunctionACallIsWrittenIn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); } $d->query("SELECT 2");');
        $finder = new SinkFinder();
        $calls = $finder->find($file, (new PdoExtension())->sinks());

        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $finder->enclosingBody($calls[0]));
        self::assertNull($finder->enclosingBody($calls[1]));
    }
}
