<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\EndMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EndMode::class)]
#[Medium]
final class EndModeTest extends TestCase
{
    public function testBindsTheRequestToAnExplicitAlternative(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA END 'g' SUSPEND FOR MIGRATE");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaEndStatement::class, $statement);
        self::assertSame(EndMode::Migrate, $statement->mode);
    }
}
