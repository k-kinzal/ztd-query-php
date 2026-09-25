<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Procedural\HelpStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Requests;
use SqlSemantics\SimpleSerializer;

#[CoversClass(Requests::class)]
#[Medium]
final class RequestsTest extends TestCase
{
    public function testWriteSpellsEachRequestFromItsOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $help = $binder->bind('HELP contents');
        self::assertInstanceOf(HelpStatement::class, $help);
        self::assertSame("HELP 'contents'", (new SimpleSerializer())->serialize($help));
        self::assertSame("IMPORT TABLE FROM 'a'", (new SimpleSerializer())->serialize($binder->bind("IMPORT  TABLE FROM 'a'")));
        self::assertSame('UNLOCK INSTANCE', (new SimpleSerializer())->serialize($binder->bind('unlock   instance')));
    }

    public function testTextEscapesQuotesAndBackslashes(): void
    {
        self::assertSame("'a''b\\\\c'", Requests::text("a'b\\c")->toString());
    }
}
