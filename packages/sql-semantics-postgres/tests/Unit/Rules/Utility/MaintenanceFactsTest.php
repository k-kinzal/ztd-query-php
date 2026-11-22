<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\MaintenanceFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(MaintenanceFacts::class)]
#[Medium]
final class MaintenanceFactsTest extends TestCase
{
    public function testTargetsReportsAColumnTheDeclaredTableLacks(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        self::assertSame(
            ['column "b" of relation "t" does not exist'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('ANALYZE t (a, b)', [$table])->facts->diagnostics),
        );
    }

    public function testTargetsClaimsNothingAboutAnUndeclaredTable(): void
    {
        $operation = (new Inspection())->diagnose('VACUUM ANALYZE t (a), u');
        self::assertInstanceOf(Vacuum::class, $operation->statement);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->targets[1])->table);
    }

    public function testHasComparesUnderTheContext(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('A'), Builtin::Int4)]);
        self::assertSame(
            ['column "a" of relation "t" does not exist'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('ANALYZE t ("A", a)', [$table])->facts->diagnostics),
        );
    }
}
