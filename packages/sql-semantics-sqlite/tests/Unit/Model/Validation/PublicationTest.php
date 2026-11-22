<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Script;
use SqlSemantics\Platform\Sqlite\Model\Validation\Publication;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use Tests\Support\Inspection;

#[CoversClass(Publication::class)]
#[Medium]
final class PublicationTest extends TestCase
{
    public function testFactsDerivesTheFactsAndRendersCorrespondingSql(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $use = new ColumnUse(new Name('a'));
        $statement = new Select([new ResultColumn($use, new Name('order'))], new TableInput(new QualifiedName(new Name('t'))));

        [$facts, $sql] = (new \Tests\Support\RenderAudit())->establish($semantics->context([$table]), $statement);

        self::assertSame('SELECT a AS `order` FROM t', $sql);
        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($use)->resolution);
        self::assertSame($table->declarations()[0]->columns[0], $facts->scalar($use)->resolution->declaration());
        self::assertSame('order', $facts->output?->fields()?->at(0)->name?->value);
    }

    public function testFactsRefusesANodeUsedAtTwoPositions(): void
    {
        $context = (new Inspection())->context([]);
        $literal = new IntegerLiteral('1');

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        (new Publication())->facts($context, new Select([new ResultColumn($literal), new ResultColumn($literal)]));
    }

    public function testRootIsTheOnlyStatementOrAScript(): void
    {
        $semantics = new Inspection();
        $first = $semantics->inspect('SELECT 1')->statement;
        $second = $semantics->inspect('SELECT 2')->statement;

        $publication = new \Tests\Support\RenderAudit();

        self::assertSame($first, $publication->root([$first]));
        $script = $publication->root([$first, $second]);
        self::assertInstanceOf(Script::class, $script);
        self::assertSame([$first, $second], $script->statements);
        $empty = $publication->root([]);
        self::assertInstanceOf(Script::class, $empty);
        self::assertSame([], $empty->statements);
    }
}
