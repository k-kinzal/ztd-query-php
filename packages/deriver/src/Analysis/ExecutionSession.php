<?php

declare(strict_types=1);

namespace Deriver\Analysis;

/**
 * Former name of the ordered execution session, kept for one release so existing callers keep working.
 * @deprecated Construct {@see \Deriver\ExecutionSession} instead; this alias is removed in the next minor release.
 * @example The former name still opens an execution session
 *     $session = new \Deriver\Analysis\ExecutionSession(new \Deriver\Project\ProjectInput([]));
 *     $session->callsTo("missing") // => []
 * @visibility public
 */
final class ExecutionSession extends \Deriver\ExecutionSession
{
}
