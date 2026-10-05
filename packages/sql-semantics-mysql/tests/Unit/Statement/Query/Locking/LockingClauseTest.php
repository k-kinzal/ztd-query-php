<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(LockingClause::class)]
#[Medium]
final class LockingClauseTest extends TestCase
{
    public function testRenderWritesTheClauses(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $clause = new LockingClause(LockStrength::Update, [new QualifiedName(new Name('t')), new QualifiedName(new Name('u'), new Name('db'))], LockedRowAction::Nowait);
        $out = new Output(new Codec($semantics->profile()->grammar));
        $clause->render($out);

        self::assertSame('FOR UPDATE OF t, db.u NOWAIT', (new Lexical())->join($out->pieces()));
        self::assertSame('SELECT a FROM t FOR SHARE SKIP LOCKED LOCK IN SHARE MODE', $semantics->analyze('select a from t for share skip locked lock in share mode')->toString());
        self::assertSame('SELECT a FROM t FOR UPDATE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t for update')->toString());
    }

    public function testATableListOnShareModeIsRejected(): void
    {
        $this->expectExceptionMessage('LOCK IN SHARE MODE takes no table list and no action.');

        new LockingClause(LockStrength::ShareMode, [], LockedRowAction::Nowait);
    }

    public function testACatalogQualifiedTableIsRejected(): void
    {
        $this->expectExceptionMessage('A table is qualified by at most a database.');

        new LockingClause(LockStrength::Update, [new QualifiedName(new Name('t'), new Name('db'), new Name('c'))]);
    }

    public function testRenderWritesTheStarOfATableWrittenWithIt(): void
    {
        $clause = new LockingClause(LockStrength::Share, [new QualifiedName(new Name('t')), new QualifiedName(new Name('u'))], null, [OptionalWords::Written, OptionalWords::Omitted]);
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        $clause->render($out);

        self::assertSame('FOR SHARE OF t.*, u', (new Lexical())->join($out->pieces()));
        self::assertSame([OptionalWords::Omitted], (new LockingClause(LockStrength::Update, [new QualifiedName(new Name('t'))]))->wildcards);
    }

    public function testAWildcardListOfAnotherLengthIsRejected(): void
    {
        $this->expectExceptionMessage('A locking clause has one wildcard form per table.');

        new LockingClause(LockStrength::Update, [new QualifiedName(new Name('t'))], null, [OptionalWords::Written, OptionalWords::Written]);
    }
}
