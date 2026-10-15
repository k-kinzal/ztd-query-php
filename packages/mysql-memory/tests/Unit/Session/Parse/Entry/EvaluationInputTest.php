<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\EvaluationInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EvaluationInput::class)]
#[Small]
final class EvaluationInputTest extends TestCase
{
    public function testErrorPreservesTheWildcardAndEarlierDatabaseDependency(): void
    {
        $session = (new Instance())->connect();
        $reader = new EvaluationInput();

        self::assertSame(1096, $reader->error(new EvaluationWildcard(), $session)->getCode());
        self::assertSame("Unknown table 'd.t'", $reader->error(new EvaluationWildcard(new QualifiedName(new Name('t'), new Name('d'))), $session)->getMessage());
        self::assertSame([[1046, 'No database selected']], $reader->error(new EvaluationWildcard(requiresDatabase: true), $session)->following);
        $session->variables->database = 'd';
        self::assertSame(1096, $reader->error(new EvaluationWildcard(requiresDatabase: true), $session)->getCode());
    }
}
