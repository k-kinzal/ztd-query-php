<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Lexical;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;

#[CoversClass(ObjectNames::class)]
#[Small]
final class ObjectNamesTest extends TestCase
{
    public function testWriteWritesTheSchemaBeforeTheName(): void
    {
        $out = new Output(new Codec());
        (new ObjectNames())->write($out, new QualifiedName(new Name('users'), new Name('main')));

        self::assertSame('main.users', (new Lexical())->join($out->pieces()));
    }

    public function testWriteWritesAnUnqualifiedNameAlone(): void
    {
        $out = new Output(new Codec());
        (new ObjectNames())->write($out, new QualifiedName(new Name('order items')));

        self::assertSame('`order items`', (new Lexical())->join($out->pieces()));
    }
}
