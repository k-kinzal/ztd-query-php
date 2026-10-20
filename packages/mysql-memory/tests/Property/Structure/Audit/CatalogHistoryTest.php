<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\CatalogHistory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Verifies that catalog witnesses repeat the original referenced identity.
 */
#[CoversNothing]
#[Small]
final class CatalogHistoryTest extends TestCase
{
    /**
     * Adds the missing catalog entry without changing the tested structure.
     */
    public function testSetupRetainsTheOriginalForeignServerIdentity(): void
    {
        $name = new Name("catalog ' あ");
        $node = new M\Server\ForeignServer\DropServer(false, $name);
        $steps = (new CatalogHistory())->steps($node, 1477);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Server\ForeignServer\CreateServer::class, $steps[0]);
        self::assertSame($name, $steps[0]->name);
        self::assertSame([], (new CatalogHistory())->steps($node, 1064));
        self::assertSame([], (new CatalogHistory())->steps(new M\Literal\NullLiteral(), 1477));
    }
}
