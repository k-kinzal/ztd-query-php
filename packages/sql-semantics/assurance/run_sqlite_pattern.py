"""Compare the fixed SQLite LIKE and GLOB predicates under a scoped observer."""

import json
from functools import partial

from run import TOOL, Failure, Worker, compare, digest, require
from run_sqlite_case import probe
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, ObserverAuthorizationError, typed
from sqlite_pattern_cases import FIXTURE, analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, forms, negatives, pattern, seeded, statement, text
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-pattern-v1.json"
CONTRACT = "6385d9428e2767c4e0ac9d53a22b1e4b593af2b7ccd696cbd9f4fe34c5e91d1f"
POLICY = "1a1488f30cd139ef50d3d6ccac0519a28211750d7029e663b56309ee7d9a4660"
observe = partial(probe, prediction=expected)


class PatternDatabase(Database):
    policy_digest = POLICY

    def __init__(self, library):
        super().__init__(library)
        path = TOOL / "sqlite-pattern-observer-v1.json"
        require(digest(path) == POLICY, "sqlite-pattern-observer-contract-mismatch")
        specification = json.loads(path.read_text())
        self.allowed_actions = frozenset(specification["allowedActions"].values())
        function = specification["allowedFunction"]
        self.function_action = function["action"]
        self.functions = frozenset(name.encode() for name in function["names"])

    def authorize(self, context, action, first, second, schema, origin):
        if action == self.function_action and origin is None and second in self.functions:
            return 0
        return super().authorize(context, action, first, second, schema, origin)

    def observe(self, sql, bindings, preserve_order=False, capture_evaluation_error=True):
        return super().observe(sql, bindings, preserve_order, capture_evaluation_error)


def observer_controls(db):
    options = sorted(row[0][1] for row in db.metadata("compile_options"))
    require("LIKE_DOESNT_MATCH_BLOBS" not in options and "CASE_SENSITIVE_LIKE" not in options, "sqlite-pattern-compilation-profile", options)
    sql = "SELECT 'a' LIKE 'A' AS ascii, 'é' LIKE 'É' AS unicode, 'a' GLOB 'A' AS glob, X'41' LIKE 'a' AS blob"
    healthy = db.observe(sql, {})
    require(healthy["rows"] == [[typed(value) for value in (1, 0, 0, 1)]] and healthy["evaluationError"] is None,
            "sqlite-pattern-builtins-mismatch", healthy)
    denied = {}
    for sql_to_deny, action in (("SELECT abs(1)", 31), ("DELETE FROM ir_range_inputs", 9)):
        try:
            db.observe(sql_to_deny, {})
        except ObserverAuthorizationError as error:
            require(error.action == action, "sqlite-pattern-observer-wrong-denial", error.action)
            denied[sql_to_deny] = {"action": action, "arguments": error.arguments}
        else:
            raise Failure("sqlite-pattern-observer-allowed", sql_to_deny)
    compare(healthy, db.observe(sql, {}))
    return {"profileProbe": healthy, "sql": sql, "compileOptions": options, "denied": denied}


def requests(specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(domain, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, item in enumerate(boundaries()):
        yield f"boundary/{index}", item, item
    for index, (sql, declarations, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": declarations, "bindings": item["bindings"]}, item


def required(_domain):
    return {"root/select-unit", "root/select-table", "root/values", "kind/LIKE", "kind/GLOB", "negated/True", "negated/False",
            "subject/pattern", "subject/column", "pattern/pattern", "pattern/blob", "pattern/parameter",
            "escape/omitted", "escape/text", "escape/concat", "escape/parameter", "escape/null"}


def controls():
    item = statement("select-unit", [[pattern("LIKE", text("a"), text("A")), pattern("GLOB", text("a"), text("A"))]])
    baseline = expected(item)
    failing = statement("select-unit", [[pattern("LIKE", text("a"), text("a"), escape=text(""))]])
    failure = expected(failing)
    substitutions = [
        (baseline, {**baseline, "rows": [[typed(0), typed(0)]]}),
        (baseline, {**baseline, "rows": [[typed(1), typed(1)]]}),
        (failure, {**failure, "evaluationError": None}),
        (failure, {**failure, "evaluationError": {"code": 1, "message": "different"}}),
    ]
    records = []
    for comparison, wrong in substitutions:
        compare(comparison, comparison)
        try:
            compare(comparison, wrong)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-pattern-control-signal", error.signal)
            records.append({"expected": comparison, "observed": wrong})
        else:
            raise Failure("sqlite-pattern-comparator-accepted", wrong)
    return records


def execute(args, specification, db, worker_factory=Worker):
    require(len(finite_ids(specification["finite"])) == specification["finite"]["identities"], "sqlite-pattern-finite-size")
    fixture = observer_controls(db)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return {**execute_schedule(args, specification, db, worker_factory, schedule, observe), "observerControls": fixture}


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    operation = "$expression->kind->value . ' ' . $pattern . $escape"
    negation = "($expression->negated ? ' NOT ' : ' ') . $expression->kind->value"
    where = "'(' . $subject . ($expression->negated ? ' NOT ' : ' ') . $expression->kind->value . ' ' . $pattern . $escape"
    return runner(args, db, [
        RenderFault("reverse-pattern-operands", renderer, where,
                    "'(' . $pattern . ($expression->negated ? ' NOT ' : ' ') . $expression->kind->value . ' ' . $subject . $escape",
                    statement("select-unit", [[pattern("LIKE", text("abc"), text("a%"))]]), "meaning-mismatch"),
        RenderFault("substitute-glob-for-like", renderer, operation, "'GLOB' . ' ' . $pattern . $escape",
                    statement("select-unit", [[pattern("LIKE", text("a"), text("A"))]]), "meaning-mismatch"),
        RenderFault("drop-like-escape", renderer, operation, "$expression->kind->value . ' ' . $pattern",
                    statement("select-unit", [[pattern("LIKE", text("a%c"), text("a!%c"), escape=text("!"))]]), "meaning-mismatch"),
        RenderFault("drop-pattern-negation", renderer, negation, "' ' . $expression->kind->value",
                    statement("select-unit", [[pattern("GLOB", text("abc"), text("a*"), True)]]), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("pattern", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, database_factory=PatternDatabase)
