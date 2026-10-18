<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\Comments;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class CommentsTest extends TestCase
{
    public function testWithinKeepsNestedStatementLocationsWithoutCompilingBodies(): void
    {
        $source = '<?php function f(){if(true){/** @var int $n */ $n=1;}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $comments = (new Comments())->within($index, 'f');
        self::assertCount(1, $comments);
        self::assertSame('/** @var int $n */', $comments[0]->text);
        self::assertSame(0, $index->graphCount());
        self::assertSame([], (new Comments())->within($index, 'missing'));
    }
}
