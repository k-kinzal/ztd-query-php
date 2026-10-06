<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Into;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\FileFormat;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(IntoOutfile::class)]
#[Small]
final class IntoOutfileTest extends TestCase
{
    public function testRenderWritesTheFileAndTheFormat(): void
    {
        $format = self::createStub(FileFormat::class);
        $outfile = new IntoOutfile(new Text('a.txt'), $format);
        $out = new Output(new Codec(GrammarRelease::MySql847));
        $outfile->render($out);

        self::assertSame("OUTFILE 'a.txt'", (new Lexical())->join($out->pieces()));
        self::assertSame($format, $outfile->format);
    }
}
