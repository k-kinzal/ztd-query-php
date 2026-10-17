<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Definition\DefinitionNameInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DefinitionNameInput::class)]
#[Small]
final class DefinitionNameInputTest extends TestCase
{
    public function testErrorResolvesTheDatabaseBeforeTheTableQualifier(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $problem = new MismatchedDefinitionName(new ColumnName(new Name('a'), new QualifiedName(new Name('other'), new Name('x'))), new QualifiedName(new Name('t')));
        $input = new DefinitionNameInput();
        self::assertSame(1046, $input->error($problem, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1102, $input->error($problem, $session)->getCode());
        $session->variables->database = 'x';
        self::assertSame(1103, $input->error($problem, $session)->getCode());
    }
}
