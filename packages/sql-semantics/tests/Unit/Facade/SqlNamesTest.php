<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Facade\SqlNames;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(SqlNames::class)]
#[Medium]
final class SqlNamesTest extends TestCase
{
    public function testTableSpellsEveryQualifierForTheProfile(): void
    {
        $profile = (new Semantics(Dialect::Sqlite))->profile();

        self::assertSame('t', SqlNames::table(new QualifiedName(new Name('t')), $profile));
        self::assertSame('main.`order items`', SqlNames::table(new QualifiedName(new Name('order items'), new Name('main')), $profile));
        self::assertSame('`c d`.s.t', SqlNames::table(new QualifiedName(new Name('t'), new Name('s'), new Name('c d')), $profile));
    }

    public function testTableSpellingDecodesToTheSameNameWhenAnalyzed(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $input = $semantics->analyze('SELECT a FROM main."select"')->singleNamedInput();

        $spelling = SqlNames::table($input->name(), $semantics->profile());
        $again = $semantics->analyze('SELECT a FROM ' . $spelling)->singleNamedInput();

        self::assertSame('main.`select`', $spelling);
        self::assertSame('select', $again->name()->name->value);
        self::assertSame('main', $again->name()->schema?->value);
    }
}
