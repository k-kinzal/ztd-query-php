<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedDeleteTargets::class)]
#[Medium]
final class RefusedDeleteTargetsTest extends TestCase
{
    public function testMessageNamesTheFirstAbsentTarget(): void
    {
        $target = new QualifiedName(new Name('x'));
        $refusal = new RefusedDeleteTargets([$target], [new TableReference(new QualifiedName(new Name('t')))]);

        self::assertSame("Unknown table 'x' in MULTI DELETE", $refusal->message());
    }

    public function testARefusalNeedsAnAbsentTarget(): void
    {
        $target = new QualifiedName(new Name('t'));
        $this->expectExceptionMessage('A refused DELETE has a target absent from its input namespace.');
        new RefusedDeleteTargets([$target], [new TableReference($target)]);
    }

    public function testDerivePreservesEarlierInputConditions(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')), new Name('same'));
        $refusal = new RefusedDeleteTargets([new QualifiedName(new Name('x'))], [$table, new TableReference(new QualifiedName(new Name('t')), new Name('same'))]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $refusal->derive($derivation);

        self::assertContains(\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable::class, array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $problem): string => $problem::class, $derivation->facts()->diagnostics));
    }
}
