<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;

#[CoversClass(SessionDatabase::class)]
#[Medium]
final class SessionDatabaseTest extends TestCase
{
    public function testNamedAnswersTheDatabaseTheCallerNamed(): void
    {
        $context = (new Semantics(Dialect::MySql, null, null, searchPath: new SearchPath('shop')))->context();

        self::assertSame('shop', (new SessionDatabase())->named($context)?->value);
    }

    public function testNamedAnswersNullWithoutACurrentDatabase(): void
    {
        $context = (new Semantics(Dialect::MySql))->context();

        self::assertSame(SessionDatabase::UNNAMED, $context->searchPath[0]->value);
        self::assertNull((new SessionDatabase())->named($context));
    }
}
