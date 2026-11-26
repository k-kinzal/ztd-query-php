<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ConflictingTables::class)]
#[Medium]
final class ConflictingTablesTest extends TestCase
{
    public function testMessageAndCandidatesOfANameTwoDeclarationsClaim(): void
    {
        $semantics = new Inspection();
        $first = $semantics->diagnose('CREATE TABLE t (a INTEGER)');
        $second = $semantics->diagnose('CREATE TABLE t (b TEXT)');
        $query = $semantics->diagnose('SELECT 1 FROM t', [$first, $second]);

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(ConflictingTables::class, $resolution);
        self::assertSame('Relation t has conflicting declarations.', $resolution->message());
        self::assertSame([$first->declarations()[0], $second->declarations()[0]], $resolution->candidates);
        self::assertSame('t', $resolution->name->name->value);
        self::assertSame([$resolution], $query->facts->diagnostics);
    }

    public function testMessageIsNotReachedWithFewerThanTwoDeclarations(): void
    {
        $table = (new Inspection())->diagnose('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $this->expectExceptionMessage('A declaration conflict has at least two declarations.');

        new ConflictingTables(new QualifiedName(new Name('t')), [$table]);
    }
}
