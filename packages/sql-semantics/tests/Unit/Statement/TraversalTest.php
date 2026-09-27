<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\Sqlite\Role\ExprForm;
use SqlSemantics\Statement\Model\Sqlite\Role\NmForm;
use SqlSemantics\Statement\Model\Sqlite\Value\ExprWithIdj_e1794d68 as ColumnName;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2 as Integer;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

#[CoversClass(Traversal::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class TraversalTest extends TestCase
{
    public function testWalkYieldsEveryValueRootFirstInWritingOrder(): void
    {
        $command = (new Semantics(SqliteDialect::Sqlite))->analyze('SELECT a FROM t WHERE b = 1')->command;
        $values = iterator_to_array(Traversal::walk($command), false);
        self::assertSame($command, $values[0]);
        $rendered = array_map(static fn (Element $value): string => Writer::render($value), array_values(array_filter($values, static fn (Element $value): bool => $value instanceof Name || $value instanceof ColumnName)));
        self::assertSame(['a', 't', 'b'], $rendered);
    }

    public function testFindCollectsTheValuesOfARoleOrClass(): void
    {
        $command = (new Semantics(SqliteDialect::Sqlite))->analyze('SELECT a FROM t WHERE b = 1')->command;
        self::assertCount(4, Traversal::find($command, ExprForm::class));
        self::assertCount(1, Traversal::find($command, NmForm::class));
        self::assertCount(2, Traversal::find($command, ColumnName::class));
        self::assertSame('1', Traversal::find($command, Integer::class)[0]->value);
        self::assertSame([], Traversal::find($command, \SqlSemantics\Statement\Model\Sqlite\Value\TermWithString_e66cd9ed::class));
    }

    public function testRewriteRebuildsFromTheLeavesUpAndKeepsWhatIsNotReplaced(): void
    {
        $statement = (new Semantics(SqliteDialect::Sqlite))->analyze('SELECT a FROM t WHERE t.b = 1 -- audited');
        $rename = static fn (Element $value): Element => $value instanceof Name && $value->name === 't' ? $value->withName('u') : $value;
        $rewritten = Traversal::rewrite($statement->command, $rename);
        self::assertSame('SELECT a FROM u WHERE u.b = 1', Writer::render($rewritten));
        self::assertSame('SELECT a FROM t WHERE t.b = 1', Writer::render($statement->command));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $rewritten);
        self::assertSame('SELECT a FROM u WHERE u.b = 1 -- audited', $statement->withCommand($rewritten)->toString());
    }

    public function testRewriteKeepsEveryValueNothingBelowWhichIsReplaced(): void
    {
        $command = (new Semantics(SqliteDialect::Sqlite))->analyze('SELECT a FROM main.t WHERE b = 1')->command;
        self::assertSame($command, Traversal::rewrite($command, static fn (Element $value): Element => $value));
        $tables = Traversal::find($command, \SqlSemantics\Statement\Model\Sqlite\Role\SeltablistForm::class);
        $rewritten = Traversal::rewrite($command, static fn (Element $value): Element => $value instanceof Integer ? new Integer('2') : $value);
        self::assertSame('SELECT a FROM main.t WHERE b = 2', Writer::render($rewritten));
        self::assertSame($tables, Traversal::find($rewritten, \SqlSemantics\Statement\Model\Sqlite\Role\SeltablistForm::class));
    }

    public function testRewriteGivesParentsTheirRewrittenChildren(): void
    {
        $command = (new Semantics(SqliteDialect::Sqlite))->analyze('SELECT 1 + 2')->command;
        $seen = [];
        Traversal::rewrite($command, static function (Element $value) use (&$seen): Element {
            $seen[] = Writer::render($value);

            return $value instanceof Integer ? $value->withValue((string) ((int) $value->value * 10)) : $value;
        });
        self::assertContains('10 + 20', $seen);
        self::assertSame('SELECT 10 + 20', end($seen));
    }
}
