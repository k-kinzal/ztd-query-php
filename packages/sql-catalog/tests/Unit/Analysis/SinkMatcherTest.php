<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\SinkMatcher;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\MysqliExtension;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\SinkCallKind;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Type\TypeShape;

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
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
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
        $sink = new SinkSpec('db.select', SinkCallKind::StaticCall, 'App\\DB', 'select', \SqlCatalog\Extension\SinkRole::Query, sqlParameter: 0);
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

    public function testReceiverMatchesReadsTheClassOutOfTheDomain(): void
    {
        $matcher = new SinkMatcher([], new ProgramIndex());
        self::assertTrue($matcher->receiverMatches(Domain::opaque(TypeShape::of(['PDO']), \SqlCatalog\Text\Origin::Property), 'PDO'));
        self::assertFalse($matcher->receiverMatches(Domain::opaque(TypeShape::of(['string']), \SqlCatalog\Text\Origin::Property), 'PDO'));
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
}
