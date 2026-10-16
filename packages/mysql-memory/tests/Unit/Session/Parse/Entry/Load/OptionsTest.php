<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Load;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Load\Options;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Options::class)]
#[Small]
final class OptionsTest extends TestCase
{
    public function testErrorOrdersCharsetDatabaseAndAssignmentBeforeTheConflictWithoutInputSql(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $options = new Options();
        $conflict = new LoadConflict(LoadRule::AssignmentsBulk);
        $table = new QualifiedName(new Name('t'));
        $qualified = new QualifiedName(new Name('t'), new Name('missing'));
        self::assertSame(1115, $options->error(new RefusedLoadOptions($table, LoadFormat::Data, $conflict, new CharsetName(new Name('no_such_charset'))), $session)->getCode());
        self::assertSame(1046, $options->error(new RefusedLoadOptions($table, LoadFormat::Data, $conflict), $session)->getCode());
        self::assertSame(1193, $options->error(new RefusedLoadOptions($qualified, LoadFormat::Data, $conflict, assignments: [new Assignment(new ColumnUse(new Name('a')), new SystemVariable(new Name('no_such_variable')))]), $session)->getCode());
        self::assertSame(1221, $options->error(new RefusedLoadOptions($qualified, LoadFormat::Data, $conflict), $session)->getCode());
    }
}
