<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\TableResolution;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TableResolution::class)]
#[Medium]
final class TableResolutionTest extends TestCase
{
    public function testTheOutcomesDependOnTheDeclarationsAndTheCompletenessOfTheContext(): void
    {
        $semantics = new Inspection();
        $first = $semantics->diagnose('CREATE TABLE t (a INTEGER)');
        $second = $semantics->diagnose('CREATE TABLE t (b TEXT)');
        $qualified = $semantics->diagnose('CREATE TABLE main.u (a INTEGER)');

        $declared = $semantics->diagnose('SELECT a FROM t', [$first]);
        $conflicting = $semantics->diagnose('SELECT a FROM t', [$first, $second]);
        $missing = $semantics->diagnose('SELECT a FROM t', []);
        $undeclared = $semantics->diagnose('SELECT a FROM t');
        $conditional = $semantics->diagnose('SELECT a FROM u', $semantics->context([$qualified], false));

        self::assertInstanceOf(DeclaredTable::class, $declared->facts->relation($declared->singleNamedInput())->table);
        self::assertInstanceOf(ConflictingTables::class, $conflicting->facts->relation($conflicting->singleNamedInput())->table);
        self::assertInstanceOf(MissingTable::class, $missing->facts->relation($missing->singleNamedInput())->table);
        self::assertInstanceOf(UndeclaredTable::class, $undeclared->facts->relation($undeclared->singleNamedInput())->table);
        self::assertInstanceOf(ConditionalTable::class, $conditional->facts->relation($conditional->singleNamedInput())->table);
    }
}
