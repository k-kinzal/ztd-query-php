<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ConflictingUpdate::class)]
#[Small]
final class ConflictingUpdateTest extends TestCase
{
    /**
     * @return iterable<string, array{ConflictingUpdate}>
     */
    public static function providerInputs(): iterable
    {
        yield 'same current database' => [new ConflictingUpdate(null, false, false, [new M\Relation\TableReference(new QualifiedName(new Name('t'))), new M\Relation\TableReference(new QualifiedName(new Name('t')))], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'))])];
    }

    #[DataProvider('providerInputs')]
    public function testMessageDescribesTheFixedNamespaceCollision(ConflictingUpdate $input): void
    {
        self::assertSame("Not unique table/alias: 't'.", $input->message());
    }

    #[DataProvider('providerInputs')]
    public function testDerivePreservesTheOriginalInputDependencies(ConflictingUpdate $input): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $derivation = new \SqlSemantics\Construction\Derivation($semantics->context([], true));
        $input->derive($derivation);
        self::assertTrue($derivation->facts()->covers($input->tables[0]));
        self::assertTrue($derivation->facts()->covers($input->tables[1]));
        self::assertContains(M\Server\Problem\NonUniqueTable::class, array_map(static fn ($diagnostic): string => $diagnostic::class, $derivation->facts()->diagnostics));
    }

    #[DataProvider('providerInputs')]
    public function testRenderPreservesTheCompleteRejectedInput(ConflictingUpdate $input): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql847));
        $input->render($out);
        self::assertSame('UPDATE t, t SET a = 1', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
