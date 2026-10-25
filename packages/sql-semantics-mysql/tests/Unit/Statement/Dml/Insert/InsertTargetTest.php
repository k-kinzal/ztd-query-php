<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(InsertTarget::class)]
#[Medium]
final class InsertTargetTest extends TestCase
{
    public function testRejectsColumnsBoundToAnotherTarget(): void
    {
        $table = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $first = new DeclaredTable($table);
        $second = new DeclaredTable($table);
        $this->expectExceptionMessage('Every destination refers to this insertion target.');
        new InsertTarget($table->name, $first, [new DestinationColumn(new Name('a'), $second, $table->columns[0])]);
    }

    public function testRejectsAResolvedTableWithAnotherName(): void
    {
        $table = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $this->expectExceptionMessage('An insertion target names its resolved table.');
        new InsertTarget(new QualifiedName(new Name('other')), new DeclaredTable($table));
    }

    public function testRenderDistinguishesAnOmittedAndEmptyColumnList(): void
    {
        $name = new QualifiedName(new Name('t'), new Name('db'));
        $resolution = new UndeclaredTable(new UndeclaredRelation($name));
        $omitted = new Output(new Codec(GrammarRelease::MySql847));
        $empty = new Output(new Codec(GrammarRelease::MySql847));
        (new InsertTarget($name, $resolution))->render($omitted);
        (new InsertTarget($name, $resolution, []))->render($empty);
        self::assertSame('db.t', (new Lexical())->join($omitted->pieces()));
        self::assertSame('db.t ()', (new Lexical())->join($empty->pieces()));
    }
}
