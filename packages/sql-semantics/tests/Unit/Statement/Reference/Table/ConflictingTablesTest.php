<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;

#[CoversClass(ConflictingTables::class)]
#[Medium]
final class ConflictingTablesTest extends TestCase
{
    public function testMessageAndCandidatesOfANameTwoDeclarationsClaim(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $first = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $second = $semantics->analyze('CREATE TABLE t (b TEXT)');
        $query = $semantics->analyze('SELECT 1 FROM t', [$first, $second]);

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(ConflictingTables::class, $resolution);
        self::assertSame('Relation t has conflicting declarations.', $resolution->message());
        self::assertSame([$first->declarations()[0], $second->declarations()[0]], $resolution->candidates);
        self::assertSame('t', $resolution->name->name->value);
        self::assertSame([$resolution], $query->facts->diagnostics);
    }

    public function testMessageIsNotReachedWithFewerThanTwoDeclarations(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $this->expectExceptionMessage('A declaration conflict has at least two declarations.');

        new ConflictingTables(new QualifiedName(new Name('t')), [$table]);
    }
}
