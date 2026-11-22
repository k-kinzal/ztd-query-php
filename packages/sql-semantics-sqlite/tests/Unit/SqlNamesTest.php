<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\SqlNames;
use Tests\Support\Inspection;

#[CoversClass(SqlNames::class)]
#[Medium]
final class SqlNamesTest extends TestCase
{
    public function testTableSpellsEveryQualifierForTheProfile(): void
    {
        $profile = (new Inspection())->profile();

        self::assertSame('t', SqlNames::table(new QualifiedName(new Name('t')), $profile));
        self::assertSame('main.`order items`', SqlNames::table(new QualifiedName(new Name('order items'), new Name('main')), $profile));
        self::assertSame('`c d`.s.t', SqlNames::table(new QualifiedName(new Name('t'), new Name('s'), new Name('c d')), $profile));
    }

    public function testTableSpellingDecodesToTheSameNameWhenAnalyzed(): void
    {
        $semantics = new Inspection();
        $input = $semantics->inspect('SELECT a FROM main."select"')->singleNamedInput();

        $spelling = SqlNames::table($input->name(), $semantics->profile());
        $again = $semantics->inspect('SELECT a FROM ' . $spelling)->singleNamedInput();

        self::assertSame('main.`select`', $spelling);
        self::assertSame('select', $again->name()->name->value);
        self::assertSame('main', $again->name()->schema?->value);
    }
}
