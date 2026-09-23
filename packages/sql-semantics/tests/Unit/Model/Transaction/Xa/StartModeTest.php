<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\StartMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StartMode::class)]
#[Medium]
final class StartModeTest extends TestCase
{
    public function testBindsTheRequestToAnExplicitAlternative(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA START 'g' RESUME");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaStartStatement::class, $statement);
        self::assertSame(StartMode::Resume, $statement->mode);
    }
}
