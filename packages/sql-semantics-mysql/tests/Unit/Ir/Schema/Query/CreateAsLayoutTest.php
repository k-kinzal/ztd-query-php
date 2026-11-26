<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateAsLayout;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(CreateAsLayout::class)]
#[Small]
final class CreateAsLayoutTest extends TestCase
{
    public function testQueryAcceptsAnOwnedVarcharSlot(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('records'), [new Identifier('v')], [new Varchar(8)]);
        $query = new Select([new ResultColumn(new TextColumnReference(0), new Identifier('copy'))], $source);

        (new CreateAsLayout())->query($query);
        $this->addToAssertionCount(1);
    }

    public function testDeclarationAcceptsTheSameNameInBothRoles(): void
    {
        $query = new Select([new ResultColumn(new NullLiteral(), new Identifier('n'))]);
        $column = new TableColumn(new Identifier('N'), IntegerStorage::Tiny, false);

        (new CreateAsLayout())->declaration($query, [$column]);
        $this->addToAssertionCount(1);
    }
}
