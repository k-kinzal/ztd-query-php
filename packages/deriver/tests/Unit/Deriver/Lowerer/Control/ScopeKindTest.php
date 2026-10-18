<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Control;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Control\GotoLowering;
use Deriver\Deriver\Lowerer\Control\ScopeKind;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ScopeKindTest extends TestCase
{
    public function testJumpsCanLeaveATryBodyButCannotSkipAFinallyBody(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $goto = new GotoLowering(new LoweringScope($index->builder('fixture.php'), $index, 'target'));
        $try = ['key' => 'region:try', 'kind' => ScopeKind::Try, 'iterator' => '', 'depth' => 0];
        $finally = ['key' => 'region:finally', 'kind' => ScopeKind::Finally, 'iterator' => '', 'depth' => 0];
        self::assertSame([$try], $goto->exited([$try], []));
        self::assertNull($goto->exited([$finally], []));
    }
}
