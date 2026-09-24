<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Pragma;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Pragma\IdentifierArgument;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IdentifierArgument::class)]
#[Medium]
final class IdentifierArgumentTest extends TestCase
{
    public function testRetainsTheBareIdentifierWithoutQuotes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA journal_mode=wal');
        self::assertInstanceOf(AssignPragmaStatement::class, $statement);
        $argument = $statement->value;
        self::assertInstanceOf(IdentifierArgument::class, $argument);
        self::assertSame('wal', $argument->name);
        self::assertSame('PRAGMA "journal_mode" = "wal"', $statement->toString());
    }

    public function testExposesTheSuppliedName(): void
    {
        self::assertSame('memory', (new IdentifierArgument('memory'))->name);
    }
}
