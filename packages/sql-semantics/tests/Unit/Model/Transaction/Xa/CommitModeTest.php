<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\CommitMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CommitMode::class)]
#[Medium]
final class CommitModeTest extends TestCase
{
    public function testBindsTheRequestToAnExplicitAlternative(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA COMMIT 'g' ONE PHASE");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaCommitStatement::class, $statement);
        self::assertSame(CommitMode::OnePhase, $statement->mode);
    }
}
