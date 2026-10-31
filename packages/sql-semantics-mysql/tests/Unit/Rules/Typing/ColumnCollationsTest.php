<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\ColumnCollations;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(ColumnCollations::class)]
#[Medium]
final class ColumnCollationsTest extends TestCase
{
    public function testMismatchAcceptsAMatchingOrSingleName(): void
    {
        $matching = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a CHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin, b CHAR(1) COLLATE latin1_bin, c CHAR(1) CHARACTER SET latin1)');
        self::assertInstanceOf(CreateTable::class, $matching);
        self::assertCount(3, $matching->elements);
        $first = $matching->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $first);

        self::assertNull((new ColumnCollations())->mismatch($first, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testMismatchIsRefusedWhileReadingAColumn(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a CHAR(1) CHARACTER SET utf8mb4 COLLATE latin1_bin)');
            self::fail('The collation belongs to another character set.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(CollationMismatch::class, $failure->problem);
            self::assertSame("COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb4'", $failure->problem->message());
        }
    }

    public function testMismatchIsRefusedWhileReadingAStoredBody(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        try {
            $semantics->analyze('CREATE PROCEDURE p() CREATE TABLE t (a CHAR(1) CHARACTER SET utf8mb4 COLLATE latin1_bin)');
            self::fail('The server refuses the pairing while reading the body.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(CollationMismatch::class, $failure->problem);
        }
        $stored = $semantics->analyze('CREATE PROCEDURE p() CREATE TABLE t (a CHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin, a INT)');
        self::assertInstanceOf(CreateProcedure::class, $stored);
        self::assertInstanceOf(DeferredCreateTable::class, $stored->body);
    }
}
