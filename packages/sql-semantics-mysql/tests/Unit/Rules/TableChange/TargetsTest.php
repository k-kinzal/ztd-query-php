<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Targets::class)]
#[Medium]
final class TargetsTest extends TestCase
{
    public function testTargetAnswersTheDeclaredColumns(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $truncate = $semantics->inspect('TRUNCATE t', [$table]);

        self::assertCount(2, $truncate->facts->relation($truncate->statement)->shape->slots);
    }

    public function testOptionalResolvesAMissingTableToAbsent(): void
    {
        $drop = (new Inspection())->inspect('DROP TABLE IF EXISTS t', []);

        self::assertInstanceOf(DropTable::class, $drop->statement);
        self::assertInstanceOf(AbsentTable::class, $drop->facts->relation($drop->statement->tables[0])->table);
    }

    public function testSameComparesTheCurrentDatabase(): void
    {
        $context = (new Inspection())->context([]);
        $current = $context->searchPath[0];

        self::assertTrue((new Targets())->same($context, new QualifiedName(new Name('t')), new QualifiedName(new Name('t'), $current)));
        self::assertFalse((new Targets())->same($context, new QualifiedName(new Name('t')), new QualifiedName(new Name('t'), new Name('other'))));
    }
}
