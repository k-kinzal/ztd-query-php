<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\RefusedUpdate;

#[CoversClass(RefusedUpdate::class)]
#[Small]
final class RefusedUpdateTest extends TestCase
{
    public function testMessageRetainsTheCompleteWrite(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1");
            self::fail('A standalone input-column contradiction must retain its write.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(RefusedUpdate::class, $failure->problem);
            self::assertSame('Duplicate column name b.', $failure->problem->message());
        }
    }
}
