<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\Event\Dependencies;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;

#[CoversClass(Dependencies::class)]
#[Small]
final class DependenciesTest extends TestCase
{
    public function testSourcesIgnoreUnusedDefinitionsButKeepTheirReferences(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $unused = $semantics->analyze("ALTER EVENT e ON SCHEDULE AT (WITH c AS (SELECT missing() FROM absent) SELECT '2030-01-01')")->statement;
        $used = $semantics->analyze('ALTER EVENT e ON SCHEDULE AT (WITH c AS (SELECT 1) SELECT * FROM c)')->statement;
        self::assertInstanceOf(AlterEvent::class, $unused);
        self::assertInstanceOf(AlterEvent::class, $used);
        self::assertNotNull($unused->schedule);
        self::assertNotNull($used->schedule);
        self::assertSame([], (new Dependencies())->sources($unused->schedule));
        self::assertCount(1, (new Dependencies())->sources($used->schedule));
    }

    public function testFeatureUsesTheReleaseVocabulary(): void
    {
        self::assertSame('Event schedule expressions which contain subqueries or stored function calls', Dependencies::feature(GrammarRelease::MySql910));
        self::assertSame('Usage of subqueries or stored function calls as part of this statement', Dependencies::feature(GrammarRelease::MySql847));
    }
}
