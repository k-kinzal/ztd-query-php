<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\InputNote;
use MySqlMemory\Session\Parse\ModernWarnings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InputNote::class)]
#[Small]
final class InputNoteTest extends TestCase
{
    public function testTheTypedNotePrecedesTheFinalRefusal(): void
    {
        $session = (new Instance())->connect();
        $note = new InputNote(SchemaError::DuplicatedValueInType, "Column '' has duplicated value 'a' in ENUM");
        (new ModernWarnings())->recorded([$note], \MySqlMemory\Error\Family\ProgramError::MissingReturn->error('d.f'), $session);
        self::assertSame([['Note', 1291, $note->message], ['Error', 1320, 'No RETURN found in FUNCTION d.f']], $session->diagnostics->conditions);
    }
}
