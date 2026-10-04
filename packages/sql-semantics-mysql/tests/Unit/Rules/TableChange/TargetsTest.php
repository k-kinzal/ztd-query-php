<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Targets::class)]
#[Medium]
final class TargetsTest extends TestCase
{
    public function testTargetAnswersTheDeclaredColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $truncate = $semantics->analyze('TRUNCATE t', [$table]);

        self::assertCount(2, $truncate->facts->relation($truncate->statement)->shape->slots);
    }

    public function testOptionalResolvesAMissingTableToAbsent(): void
    {
        $drop = (new Semantics(Dialect::MySql))->analyze('DROP TABLE IF EXISTS t', []);

        self::assertInstanceOf(DropTable::class, $drop->statement);
        self::assertInstanceOf(AbsentTable::class, $drop->facts->relation($drop->statement->tables[0])->table);
    }

    public function testSameComparesTheCurrentDatabase(): void
    {
        $context = (new Semantics(Dialect::MySql))->context([]);
        $current = $context->searchPath[0];

        self::assertTrue((new Targets())->same($context, new QualifiedName(new Name('t')), new QualifiedName(new Name('t'), $current)));
        self::assertFalse((new Targets())->same($context, new QualifiedName(new Name('t')), new QualifiedName(new Name('t'), new Name('other'))));
    }
}
