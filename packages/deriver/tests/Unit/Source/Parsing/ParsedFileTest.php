<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Parsing;

use Deriver\Project\TargetProfile;
use Deriver\Source\Parsing\ParseCache;
use Deriver\Source\Parsing\ParsedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Parsing\ParsedFile
 */
#[CoversClass(ParsedFile::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[Small]
final class ParsedFileTest extends TestCase
{
    public function testCopyPreventsSharedParserNodeMutation(): void
    {
        $tree = (new \Deriver\Source\Parsing\SourceParser())->parse('<?php return 1;', new TargetProfile());
        $copy = $tree->copy();
        self::assertNotSame($tree->nodes[0], $copy->nodes[0]);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $copy->nodes[0]);
        $changed = $copy->nodes[0];
        $changed->expr = new \PhpParser\Node\Scalar\Int_(99);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $tree->nodes[0]);
        self::assertInstanceOf(\PhpParser\Node\Scalar\Int_::class, $tree->nodes[0]->expr);
        self::assertSame(1, $tree->nodes[0]->expr->value);
    }
}
