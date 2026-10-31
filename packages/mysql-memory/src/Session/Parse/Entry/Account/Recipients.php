<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Account;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\ErrorNumbers;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Parse\Entry\InputNote;
use MySqlMemory\Session\Session;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;

/**
 * Notes the recipients the server reads before it refuses a privilege level.
 *
 * The server parses each `user@host` of GRANT and REVOKE as it reads it and
 * warns at once about a host name it cannot resolve; the level check follows
 * once the statement is read (verified on 8.4.7: `GRANT SUPER ON d.t TO u@host`
 * answers 1287, 1285, 1144).
 *
 * @visibility MySqlMemory
 */
final class Recipients
{
    /**
     * Answers the host-resolution notes of the recipients written before the refusal boundary.
     *
     * @return list<array{int, InputNote}> Each note at the end of its recipient
     */
    public function notes(RejectedInput $failure, Node $tree, Session $session): array
    {
        if (!$failure->problem instanceof MisplacedPrivilege) {
            return [];
        }
        $profile = $session->semantics()->profile();
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $names = new Names($session->settings()->release());
        $notes = [];
        foreach ($tree->find('user') as $user) {
            $end = (new SourceRanges())->span($user)[1] ?? null;
            if ($end === null || $end > $failure->end) {
                continue;
            }
            $diagnostics = new Diagnostics();
            $names->resolve($names->identity($lowering->users->account($user), $session), $diagnostics);
            foreach ($diagnostics->conditions as $condition) {
                $notes[] = [$end, new InputNote(ErrorNumbers::from($condition[1]), $condition[2])];
            }
        }
        return $notes;
    }
}
