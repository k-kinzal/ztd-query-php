<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;

#[CoversClass(SetQuantifier::class)]
#[Medium]
final class SetQuantifierTest extends TestCase
{
    public function testCasesAreReadFromASelectionAndWrittenBack(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $distinct = $semantics->analyze('select distinct a from t');
        $all = $semantics->analyze('select all a from t');
        $plain = $semantics->analyze('select a from t');

        self::assertInstanceOf(Select::class, $distinct->statement);
        self::assertSame(SetQuantifier::Distinct, $distinct->statement->quantifier);
        self::assertSame('SELECT DISTINCT a FROM t', $distinct->toString());
        self::assertInstanceOf(Select::class, $all->statement);
        self::assertSame(SetQuantifier::All, $all->statement->quantifier);
        self::assertSame('SELECT ALL a FROM t', $all->toString());
        self::assertInstanceOf(Select::class, $plain->statement);
        self::assertNull($plain->statement->quantifier);
        self::assertSame(['DISTINCT', 'ALL'], array_map(static fn (SetQuantifier $quantifier): string => $quantifier->value, SetQuantifier::cases()));
    }
}
