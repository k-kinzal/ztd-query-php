<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Parser\Node;

use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Sources\Source;
use PhpParser\Node;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
#[UsesClass(Source::class)]
#[Small]
final class FileTest extends TestCase
{
    public function testGetTypeDistinguishesTheRootFromStatementsAndExpressions(): void
    {
        $value = new Int_(42);
        $statement = new Return_($value);
        $file = new File(new Source('a.php', '<?php return 42;'), [$statement]);
        self::assertSame('File', $file->getType());
        self::assertSame(['File', 'Stmt_Return', 'Scalar_Int'], array_map(static fn (Node $node): string => $node->getType(), (new NodeFinder())->findInstanceOf([$file], Node::class)));
    }

    public function testGetSubNodeNamesAllowsTraversalFromTheRootToNestedExpressions(): void
    {
        $value = new Int_(42);
        $file = new File(new Source('a.php', '<?php return 42;'), [new Return_($value)]);
        self::assertSame(['stmts'], $file->getSubNodeNames());
        self::assertSame([$value], (new NodeFinder())->findInstanceOf([$file], Int_::class));
    }

    public function testGetStartLineAndOtherPositionsDescribeTheCompleteSource(): void
    {
        $source = new Source('empty.php', "<?php\r\n\r\n");
        $file = new File($source, []);
        self::assertSame($source, $file->source);
        self::assertSame(1, $file->getStartLine());
        self::assertSame(3, $file->getEndLine());
        self::assertSame(0, $file->getStartFilePos());
        self::assertSame(strlen($source->contents) - 1, $file->getEndFilePos());
    }
}
