<?php

declare(strict_types=1);

namespace Deriver;

/**
 * Isolated migration API for execution analysis. Candidate expansion never calls it.
 * The session validates the project, runs the ordered execution engine for each query and retains
 * execution results; see {@see Session\ExecutionSession} for the behaviour it inherits.
 * @example Explicit execution analysis
 *     $session = new \Deriver\ExecutionSession(new \Deriver\Project\ProjectInput([]));
 *     $session->callsTo("missing") // => []
 * @visibility public
 */
class ExecutionSession extends Session\ExecutionSession
{
}
