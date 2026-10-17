<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Parsing;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\Parsing\ParsedFile;
use Deriver\Source\Parsing\SourceParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceParser::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[Small]
final class SourceParserTest extends TestCase
{
    public function testParseCapturesMalformedSourceWithoutExecutingIt(): void
    {
        $tree = (new SourceParser())->parse('<?php function {', new TargetProfile());
        self::assertSame([], $tree->nodes);
        self::assertNotEmpty($tree->errors);
        self::assertSame(1, $tree->errors[0]['line']);
    }
    public function testParseDoesNotExportNewerSyntaxIntoTheTargetWorld(): void
    {
        $tree = (new SourceParser())->parse('<?php function target(){r(?);}', new TargetProfile());
        self::assertSame([], $tree->nodes);
        self::assertNotEmpty($tree->errors);
    }
    public function testParseRejectsDeepChainsWithoutRecursiveVisitorFailure(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SourceParser())->parse('<?php return '.str_repeat('1+', 1000).'1;', new TargetProfile());
    }
    public function testParseRejectsOversizedBytesBeforeTokenization(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SourceParser())->parse('<?php return 1;', new TargetProfile(), new SourceLimits(fileBytes: 2));
    }
    public function testParseIsDeterministicForTheSameBytesAndProfile(): void
    {
        $parser = new SourceParser();
        $first = $parser->parse('<?php namespace A; function f($x) { return $x + 1; }', new TargetProfile());
        $second = $parser->parse('<?php namespace A; function f($x) { return $x + 1; }', new TargetProfile());
        self::assertSame(serialize($first), serialize($second));
        self::assertSame([], $first->errors);
    }
}
