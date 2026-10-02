<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\MatchName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceAction;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceArgument;

#[CoversNothing]
#[Medium]
final class ReferenceArgumentTest extends TestCase
{
    public function testRenderWritesEveryKindOfClauseInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent ON UPDATE CASCADE MATCH simple ON DELETE RESTRICT)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $clause = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ForeignKeyClause::class, $clause);
        self::assertContainsOnlyInstancesOf(ReferenceArgument::class, $clause->arguments);
        self::assertInstanceOf(ReferenceAction::class, $clause->arguments[0]);
        self::assertInstanceOf(MatchName::class, $clause->arguments[1]);
        self::assertInstanceOf(ReferenceAction::class, $clause->arguments[2]);
        self::assertSame('CREATE TABLE c (p REFERENCES parent ON UPDATE CASCADE MATCH simple ON DELETE RESTRICT)', $operation->toString());
    }
}
