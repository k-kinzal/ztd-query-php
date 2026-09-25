<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\SinkMatcher;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Extension\ExtensionRegistry;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\ProgramIndexBuilder;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Php\TypeReader;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Extension\Mysqli\MysqliExtension;
use SqlCatalog\Extension\Pdo\PdoExtension;

#[CoversClass(SinkMatcher::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(MysqliExtension::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(TypeReader::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelSet::class)]
final class SinkMatcherTest extends TestCase
{
    public function testMatchMethodFindsTheCallOnAKnownReceiver(): void
    {
        $matcher = new SinkMatcher((new PdoExtension())->sinks(), new ProgramIndex());
        self::assertSame('pdo.query', $matcher->matchMethod(Domain::of(new ObjectTerm('PDO')), 'query')?->id);
        self::assertNull($matcher->matchMethod(Domain::of(new ObjectTerm('App\\Mailer')), 'query'));
    }

    public function testMatchMethodIgnoresAReceiverNothingIsKnownAbout(): void
    {
        $matcher = new SinkMatcher((new PdoExtension())->sinks(), new ProgramIndex());
        self::assertNull($matcher->matchMethod(Domain::unknown(), 'query'));
    }

    public function testMatchStaticFindsTheCallOnAKnownClass(): void
    {
        $sink = new SinkSpec('db.select', SinkCallKind::StaticCall, 'App\\DB', 'select', \SqlCatalog\Core\Extension\SinkRole::Query, sqlParameter: 0);
        $matcher = new SinkMatcher([$sink], new ProgramIndex());
        self::assertSame('db.select', $matcher->matchStatic('App\\DB', 'select')?->id);
        self::assertNull($matcher->matchStatic('App\\Other', 'select'));
    }

    public function testMatchFunctionFindsTheFreeFunction(): void
    {
        $matcher = new SinkMatcher((new MysqliExtension())->sinks(), new ProgramIndex());
        self::assertSame('mysqli.fn.query', $matcher->matchFunction('mysqli_query')?->id);
        self::assertNull($matcher->matchFunction('sprintf'));
    }

    public function testByNameCollectsEveryCallWrittenWithThatName(): void
    {
        $matcher = new SinkMatcher((new PdoExtension())->sinks(), new ProgramIndex());
        self::assertCount(1, $matcher->byName(SinkCallKind::Method, 'prepare'));
        self::assertSame([], $matcher->byName(SinkCallKind::FunctionCall, 'prepare'));
    }

    public function testByNameKeepsEveryCallOfThatName(): void
    {
        $matcher = new SinkMatcher([
            new SinkSpec('a.run', SinkCallKind::Method, 'App\\A', 'run', \SqlCatalog\Core\Extension\SinkRole::Query, sqlParameter: 0),
            new SinkSpec('b.run', SinkCallKind::Method, 'App\\B', 'run', \SqlCatalog\Core\Extension\SinkRole::Query, sqlParameter: 0),
        ], new ProgramIndex());

        self::assertSame(['a.run', 'b.run'], array_map(
            static fn (SinkSpec $sink): string => $sink->id,
            $matcher->byName(SinkCallKind::Method, 'run'),
        ));
    }

    public function testClassMatchesIgnoresALeadingBackslashAndTheCaseOfTheName(): void
    {
        $matcher = new SinkMatcher([], new ProgramIndex());

        self::assertTrue($matcher->classMatches('\\App\\Repository', 'App\\Repository'));
        self::assertTrue($matcher->classMatches('App\\Repository', '\\App\\Repository'));
        self::assertTrue($matcher->classMatches('App\\Repository', 'app\\repository'));
        self::assertFalse($matcher->classMatches('App\\A', 'App\\B'));
        self::assertFalse($matcher->classMatches('App\\B', 'App\\A'));
    }

    public function testClassMatchesReadsTheRunningProcessOnlyForAClassThatIsRelated(): void
    {
        $matcher = new SinkMatcher([], new ProgramIndex());

        self::assertTrue($matcher->classMatches('ArrayIterator', 'Traversable'));
        self::assertFalse($matcher->classMatches('PDOStatement', 'PDO'));
    }

    public function testClassMatchesNeverLoadsAClassOfTheAnalyzedSource(): void
    {
        $requested = new ArrayObject();
        $loader = static function (string $class) use ($requested): void {
            $requested->append($class);
        };
        spl_autoload_register($loader);

        $matches = (new SinkMatcher([], new ProgramIndex()))->classMatches('App\\NeverDeclared', 'PDO');
        spl_autoload_unregister($loader);

        self::assertFalse($matches);
        self::assertSame([], $requested->getArrayCopy());
    }

    public function testReceiverMatchesReadsTheClassOutOfTheDomain(): void
    {
        $matcher = new SinkMatcher([], new ProgramIndex());
        self::assertTrue($matcher->receiverMatches(Domain::opaque(TypeShape::of(['PDO']), \SqlCatalog\Core\Text\Origin::Property), 'PDO'));
        self::assertFalse($matcher->receiverMatches(Domain::opaque(TypeShape::of(['string']), \SqlCatalog\Core\Text\Origin::Property), 'PDO'));
    }

    public function testClassMatchesFollowsInheritanceDeclaredInTheAnalyzedSource(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php namespace App; class ZtdPdo extends \\PDO {}',
        )]);
        $matcher = new SinkMatcher([], $index);
        self::assertTrue($matcher->classMatches('App\\ZtdPdo', 'PDO'));
    }

    public function testClassMatchesFollowsInheritanceOfTheRunningProcess(): void
    {
        $matcher = new SinkMatcher([], new ProgramIndex());
        self::assertTrue($matcher->classMatches('PDOStatement', 'PDOStatement'));
        self::assertFalse($matcher->classMatches('App\\Unknown', 'PDO'));
    }

    public function testModelsSaysWhetherAnExtensionAlreadyStandsForTheReceiver(): void
    {
        $matcher = new SinkMatcher((new PdoExtension())->sinks(), new ProgramIndex());

        self::assertTrue($matcher->models(Domain::of(new ObjectTerm('PDO'))));
        self::assertFalse($matcher->models(Domain::of(new ObjectTerm('App\\Repository'))));
    }

    public function testClassMatchesUsesRegisteredTypeRelationsOnly(): void
    {
        $models = new \SqlCatalog\Core\Extension\Model\ModelSet(classRelations: [static fn (string $class, string $expected): bool => $class === 'VendorConnection' && $expected === 'Driver']);
        self::assertTrue((new SinkMatcher([], new ProgramIndex(), $models))->classMatches('VendorConnection', 'Driver'));
        self::assertFalse((new SinkMatcher([], new ProgramIndex()))->classMatches('VendorConnection', 'Driver'));
    }
}
