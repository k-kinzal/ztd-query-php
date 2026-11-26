<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SessionDatabase::class)]
#[Medium]
final class SessionDatabaseTest extends TestCase
{
    public function testNamedAnswersTheDatabaseTheCallerNamed(): void
    {
        $context = (new Inspection(null, null, searchPath: new SearchPath('shop')))->context();

        self::assertSame('shop', (new SessionDatabase())->named($context)?->value);
    }

    public function testNamedAnswersNullWithoutACurrentDatabase(): void
    {
        $context = (new Inspection())->context();

        self::assertSame(SessionDatabase::UNNAMED, $context->searchPath[0]->value);
        self::assertNull((new SessionDatabase())->named($context));
    }
}
