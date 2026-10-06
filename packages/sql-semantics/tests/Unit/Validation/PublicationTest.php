<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Script;
use SqlSemantics\Validation\Publication;

#[CoversClass(Publication::class)]
#[Medium]
final class PublicationTest extends TestCase
{
    public function testEstablishDerivesTheFactsAndRendersCorrespondingSql(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $use = new ColumnUse(new Name('a'));
        $statement = new Select([new ResultColumn($use, new Name('order'))], new TableInput(new QualifiedName(new Name('t'))));

        [$facts, $sql] = (new Publication())->establish($semantics->context([$table]), $statement);

        self::assertSame('SELECT a AS `order` FROM t', $sql);
        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($use)->resolution);
        self::assertSame($table->declarations()[0]->columns[0], $facts->scalar($use)->resolution->declaration());
        self::assertSame('order', $facts->output?->fields()?->at(0)->name?->value);
    }

    public function testEstablishRefusesANodeUsedAtTwoPositions(): void
    {
        $context = (new Semantics(Dialect::Sqlite))->context([]);
        $literal = new IntegerLiteral('1');

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        (new Publication())->establish($context, new Select([new ResultColumn($literal), new ResultColumn($literal)]));
    }

    public function testRootIsTheOnlyStatementOrAScript(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $first = $semantics->analyze('SELECT 1')->statement;
        $second = $semantics->analyze('SELECT 2')->statement;

        $publication = new Publication();

        self::assertSame($first, $publication->root([$first]));
        $script = $publication->root([$first, $second]);
        self::assertInstanceOf(Script::class, $script);
        self::assertSame([$first, $second], $script->statements);
        $empty = $publication->root([]);
        self::assertInstanceOf(Script::class, $empty);
        self::assertSame([], $empty->statements);
    }
}
