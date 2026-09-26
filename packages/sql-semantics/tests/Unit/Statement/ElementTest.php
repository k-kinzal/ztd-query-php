<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class ElementTest extends TestCase
{
    public function testChildrenListTheValuesInWritingOrder(): void
    {
        $command = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t')->command;
        self::assertInstanceOf(\SqlSemantics\Statement\Model\Sqlite\Value\EcmdWithCmdxSemi_b7577a8f::class, $command);
        self::assertCount(1, $command->children());
        self::assertSame($command->cmdx, $command->children()[0]);
        $select = $command->cmdx;
        self::assertInstanceOf(\SqlSemantics\Statement\Model\Sqlite\Value\OneselectWithSelectDistinctSelcollistFromWhereOptGroupbyOptHavingOptOrderbyOptLimitOpt_218e0475::class, $select);
        self::assertSame([$select->distinct, $select->projections, $select->from, $select->where, $select->groupBy, $select->having, $select->orderBy, $select->pagination], $select->children());
        $leaf = new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('1');
        self::assertSame([], $leaf->children());
        self::assertSame($leaf, $leaf->map(static fn (\SqlSemantics\Statement\Element $child): \SqlSemantics\Statement\Element => $child));
        $choice = \SqlSemantics\Statement\Model\Sqlite\Choice\SortorderChoice_01affc0e::from('DESC');
        self::assertSame([], $choice->children());
        self::assertSame($choice, $choice->map(static fn (\SqlSemantics\Statement\Element $child): \SqlSemantics\Statement\Element => $child));
    }

    public function testMapRebuildsAroundReplacementsAndAnswersLeavesThemselves(): void
    {
        $command = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t')->command;
        $mapped = $command->map(static fn (\SqlSemantics\Statement\Element $child): \SqlSemantics\Statement\Element => $child);
        self::assertNotSame($command, $mapped);
        self::assertEquals($command, $mapped);
        $leaf = new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('1');
        self::assertSame($leaf, $leaf->map(static fn (\SqlSemantics\Statement\Element $child): \SqlSemantics\Statement\Element => $child));
        $choice = \SqlSemantics\Statement\Model\Sqlite\Choice\SortorderChoice_01affc0e::from('DESC');
        self::assertSame($choice, $choice->map(static fn (\SqlSemantics\Statement\Element $child): \SqlSemantics\Statement\Element => $child));
    }

    public function testWriteWithConcreteCommand(): void
    {
        $command = new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('COMMIT', new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWith_6ac05548());
        $writer = new \SqlSemantics\Statement\Writer();
        $command->write($writer);
        self::assertSame('COMMIT', $writer->toString());
    }
}
