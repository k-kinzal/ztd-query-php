<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(TableResolution::class)]
#[Medium]
final class TableResolutionTest extends TestCase
{
    public function testTheOutcomesDependOnTheDeclarationsAndTheCompletenessOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $first = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $second = $semantics->analyze('CREATE TABLE t (b TEXT)');
        $qualified = $semantics->analyze('CREATE TABLE main.u (a INTEGER)');

        $declared = $semantics->analyze('SELECT a FROM t', [$first]);
        $conflicting = $semantics->analyze('SELECT a FROM t', [$first, $second]);
        $missing = $semantics->analyze('SELECT a FROM t', []);
        $undeclared = $semantics->analyze('SELECT a FROM t');
        $conditional = $semantics->analyze('SELECT a FROM u', $semantics->context([$qualified], false));

        self::assertInstanceOf(DeclaredTable::class, $declared->singleNamedInput()->relationInformation()->table);
        self::assertInstanceOf(ConflictingTables::class, $conflicting->singleNamedInput()->relationInformation()->table);
        self::assertInstanceOf(MissingTable::class, $missing->singleNamedInput()->relationInformation()->table);
        self::assertInstanceOf(UndeclaredTable::class, $undeclared->singleNamedInput()->relationInformation()->table);
        self::assertInstanceOf(ConditionalTable::class, $conditional->singleNamedInput()->relationInformation()->table);
    }
}
