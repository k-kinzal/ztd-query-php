<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\RewriteRefusal;

#[CoversClass(RewriteRefusal::class)]
#[UsesClass(ZtdConfig::class)]
#[UsesClass(RewritePlan::class)]
#[UsesClass(UnsupportedSqlException::class)]
#[UsesClass(UnknownSchemaException::class)]
#[UsesClass(DatabaseException::class)]
final class RewriteRefusalTest extends TestCase
{
    public function testForUnsupportedRaisesWhereTheCallerAskedToBeTold(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unsupportedBehavior: UnsupportedSqlBehavior::Exception));

        $this->expectException(DatabaseException::class);

        $refusals->forUnsupported(new UnsupportedSqlException('SELECT 1', 'why'), 'SELECT 1');
    }

    public function testForUnsupportedAnswersAPlanThatDoesNothingWhereTheCallerAllowsThat(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unsupportedBehavior: UnsupportedSqlBehavior::Ignore));

        $plan = $refusals->forUnsupported(new UnsupportedSqlException('SELECT 1', 'why'), 'SELECT 1');

        self::assertSame([QueryKind::SKIPPED, 'SELECT 1'], [$plan->kind(), $plan->sql()]);
    }

    public function testForUnsupportedSaysSomethingWhereTheCallerAskedToBeNoticed(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unsupportedBehavior: UnsupportedSqlBehavior::Notice));
        $said = null;
        set_error_handler(static function (int $level, string $message) use (&$said): bool {
            $said = $message;

            return true;
        }, E_USER_NOTICE);

        try {
            $refusals->forUnsupported(new UnsupportedSqlException('SELECT 1', 'why'), 'SELECT 1');
        } finally {
            restore_error_handler();
        }

        self::assertSame('[ZTD Notice] Unsupported SQL ignored: SELECT 1', $said);
    }

    public function testForUnknownSchemaRaisesWhereTheCallerAskedToBeTold(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unknownSchemaBehavior: UnknownSchemaBehavior::Exception));

        $this->expectException(DatabaseException::class);

        $refusals->forUnknownSchema(new UnknownSchemaException('SELECT 1', 't'), 'SELECT 1', 'SELECT 1 WHERE 0');
    }

    public function testForUnknownSchemaLetsTheStatementThroughWhereTheCallerAsksForThat(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unknownSchemaBehavior: UnknownSchemaBehavior::Passthrough));

        $plan = $refusals->forUnknownSchema(new UnknownSchemaException('SELECT 1', 't'), 'SELECT 1', 'SELECT 1 WHERE 0');

        self::assertSame([QueryKind::READ, 'SELECT 1'], [$plan->kind(), $plan->sql()]);
    }

    public function testForUnknownSchemaReadsNothingBackWhereTheCallerAsksForThat(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unknownSchemaBehavior: UnknownSchemaBehavior::EmptyResult));

        $plan = $refusals->forUnknownSchema(new UnknownSchemaException('SELECT 1', 't'), 'SELECT 1', 'SELECT 1 WHERE 0');

        self::assertSame([QueryKind::READ, 'SELECT 1 WHERE 0'], [$plan->kind(), $plan->sql()]);
    }

    public function testForUnknownSchemaSaysSomethingWhereTheCallerAskedToBeNoticed(): void
    {
        $refusals = new RewriteRefusal(new ZtdConfig(unknownSchemaBehavior: UnknownSchemaBehavior::Notice));
        $said = null;
        set_error_handler(static function (int $level, string $message) use (&$said): bool {
            $said = $message;

            return true;
        }, E_USER_NOTICE);

        try {
            $refusals->forUnknownSchema(new UnknownSchemaException('SELECT 1', 't'), 'SELECT 1', 'SELECT 1 WHERE 0');
        } finally {
            restore_error_handler();
        }

        self::assertSame('[ZTD Notice] Unknown table referenced: t', $said);
    }
}
