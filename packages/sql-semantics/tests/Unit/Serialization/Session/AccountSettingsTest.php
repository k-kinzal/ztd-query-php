<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\AccountSettings;

#[CoversClass(AccountSettings::class)]
#[Medium]
final class AccountSettingsTest extends TestCase
{
    public function testWriteRetainsTheConcreteRequestOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD='new'", strict: false);
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        self::assertSame("SET PASSWORD = 'new'", $statement->toString());
    }
}
