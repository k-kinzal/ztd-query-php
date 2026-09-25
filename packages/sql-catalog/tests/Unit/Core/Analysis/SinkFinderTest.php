<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\SinkFinder;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Extension\Pdo\PdoExtension;

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

    public function testFindAllCollectsEveryDatabaseCallButTheOnesThatComposeAStatement(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d, PDOStatement $s): void { $d->query("a"); $s->execute(); $s->bindValue(1, 2); $d->prepare("b"); $d->interpolate("c"); \\mysqli_query($d, "d"); $d->fetchAll(); }',
        );
        $finder = new SinkFinder();
        $sinks = [
            new SinkSpec('t.query', SinkCallKind::Method, 'PDO', 'query', SinkRole::Query),
            new SinkSpec('t.execute', SinkCallKind::Method, 'PDOStatement', 'execute', SinkRole::Execute),
            new SinkSpec('t.bind', SinkCallKind::Method, 'PDOStatement', 'BindValue', SinkRole::Bind),
            new SinkSpec('t.prepare', SinkCallKind::Method, 'PDO', 'prepare', SinkRole::Prepare),
            new SinkSpec('t.compose', SinkCallKind::Method, 'PDO', 'interpolate', SinkRole::Compose),
            new SinkSpec('t.mysqli', SinkCallKind::FunctionCall, null, '\\mysqli_query', SinkRole::Query),
        ];

        self::assertSame(
            ['query', 'execute', 'bindValue', 'prepare', 'mysqli_query'],
            array_map(static fn (Expr\CallLike $call): ?string => $finder->nameOf($call), $finder->findAll($file, $sinks)),
        );
    }

    public function testFindAllFindsNothingWhenEverySinkComposesAStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->interpolate("a"); $d->query("b"); }');

        self::assertSame([], (new SinkFinder())->findAll($file, [
            new SinkSpec('t.compose', SinkCallKind::Method, 'PDO', 'interpolate', SinkRole::Compose),
        ]));
    }

    public function testFindAllTakesInTheCallsThatOnlyBindValues(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $s = $d->prepare("SELECT ?"); $s->bindValue(1, 2); $s->execute(); }',
        );
        $finder = new SinkFinder();
        $sinks = (new PdoExtension())->sinks();

        self::assertCount(1, $finder->find($file, $sinks));
        self::assertCount(3, $finder->findAll($file, $sinks));
    }

    public function testNamesOfKeepsOnlyTheCallsThatCarryAStatement(): void
    {
        $names = (new SinkFinder())->namesOf((new PdoExtension())->sinks());

        self::assertArrayHasKey('query', $names);
        self::assertArrayHasKey('prepare', $names);
        self::assertArrayNotHasKey('bindvalue', $names);
        self::assertArrayNotHasKey('execute', $names);
    }

    public function testNamesOfWritesEachNameInLowerCaseWithoutALeadingBackslash(): void
    {
        $names = (new SinkFinder())->namesOf([
            new SinkSpec('t.query', SinkCallKind::Method, 'Db', 'Query', SinkRole::Query),
            new SinkSpec('t.prepare', SinkCallKind::FunctionCall, null, '\\Db_Prepare', SinkRole::Prepare),
            new SinkSpec('t.execute', SinkCallKind::Method, 'Db', 'execute', SinkRole::Execute),
        ]);

        self::assertSame(['query' => true, 'db_prepare' => true], $names);
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
