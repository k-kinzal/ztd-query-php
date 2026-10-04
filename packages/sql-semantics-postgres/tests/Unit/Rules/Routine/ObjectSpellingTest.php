<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ObjectSpelling::class)]
#[Small]
final class ObjectSpellingTest extends TestCase
{
    public function testKindWritesConstraintForADomainConstraint(): void
    {
        $domain = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ObjectSpelling())->kind($domain, ObjectKind::DomainConstraint);
        $wrapper = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ObjectSpelling())->kind($wrapper, ObjectKind::ForeignDataWrapper);
        self::assertSame(['CONSTRAINT', 'FOREIGN DATA WRAPPER'], [(new Lexical())->join($domain->pieces()), (new Lexical())->join($wrapper->pieces())]);
    }
}
