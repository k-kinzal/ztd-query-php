<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\EventCompletion;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventCompletion::class)]
#[Medium]
final class EventCompletionTest extends TestCase
{
    public function testDefaultsToDroppingTheEvent(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $omitted = $binder->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO DO 1');
        $explicit = $binder->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP ON COMPLETION NOT PRESERVE DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $omitted);
        self::assertInstanceOf(CreateEventStatement::class, $explicit);
        self::assertSame(EventCompletion::Drop, $omitted->completion);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($omitted), (new \SqlSemantics\SimpleSerializer())->serialize($explicit));
    }
}
