<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\RecoveryField;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RecoveryField::class)]
#[Medium]
final class RecoveryFieldTest extends TestCase
{
    public function testRecoveryResultsRetainTheirFieldRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('XA RECOVER');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaRecoverStatement::class, $statement);
        $fields = array_column(array_column($statement->resultColumns(), 'expression'), 'field');
        self::assertSame([RecoveryField::Format, RecoveryField::GlobalLength, RecoveryField::BranchLength, RecoveryField::Data], $fields);
    }
}
