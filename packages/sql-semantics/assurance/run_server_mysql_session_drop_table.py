"""Compare MySQL table removal in persistent and session-local namespaces."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_ordered_table_rename import DIAGNOSTIC, SEMANTIC, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "mysql-session-drop-table"
OBSERVER = "mysql_session_drop_observer.php"
CONTRACT_NAME = "mysql-session-drop-table-v2.json"
CONTRACT = "4fc18dcf31bb099cbb0b61b86f52c874a49f62acffe554e802ea6a41ea604efe"
COMPLETE_SEEDS = True
SCHEMA, DECOY, NAME = "ir_fixture", "ir_sdrop_decoy", "ir_sdrop_data"
SESSION = "temporary-table"
AXES = ("fixture", "targets", "policy")
ROLE_MISMATCH = "mysql-drop-target-role"
INVALID_PUBLISHED = "mysql-drop-invalid-published"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-session-drop-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def target_scope(form):
    return {
        "primary": [SCHEMA], "decoy": [DECOY],
        "primary-first": [SCHEMA, DECOY], "decoy-first": [DECOY, SCHEMA],
    }[form]


def session_names(fixture):
    return {
        "shadow-primary": [SCHEMA], "shadow-decoy": [DECOY],
        "shadow-both": [SCHEMA, DECOY], "temporary-only-primary": [SCHEMA],
    }[fixture]


def case(tag, fixture, form, policy, variant=0):
    temporary = policy.startswith("temporary-")
    optional = policy.endswith("-optional")
    sessions = session_names(fixture)
    targets = []
    for schema in target_scope(form):
        kind = SESSION if schema in sessions else "absent" if temporary else "table"
        targets.append([schema, NAME, kind])
    return {"dialect": "mysql", "release": tag, "fixture": fixture, "variant": variant,
            "drop": {"targets": targets, "temporaryOnly": temporary, "ifExists": optional}}


def required_temporary_ids():
    return {
        "shadow-primary": {"primary"}, "shadow-decoy": {"decoy"},
        "shadow-both": {"primary", "decoy", "primary-first", "decoy-first"},
        "temporary-only-primary": {"primary"},
    }


def finite_ids(_, spec, _tag):
    plan = spec["finite"]
    ordinary_or_optional = set(plan["policy"]) - {"temporary-required"}
    expected = set(product(plan["fixture"], plan["targets"], ordinary_or_optional))
    expected.update((fixture, form, "temporary-required") for fixture, forms in required_temporary_ids().items() for form in forms)
    require(len(expected) == plan["identities"], "mysql-session-drop-grid-size")
    return expected


def finite_cases(tag, spec):
    for fixture, form, policy in product(*(spec["finite"][axis] for axis in AXES)):
        item = case(tag, fixture, form, policy)
        shape = item["drop"]
        if shape["ifExists"] or all(kind != "absent" for _, _, kind in shape["targets"]):
            yield item


def finite_id(_, response, item):
    shape = response["mysqlDrop"]
    names = [schema for schema, _, _ in shape["targets"]]
    matching = [name for name in ("primary", "decoy", "primary-first", "decoy-first") if target_scope(name) == names]
    require(len(matching) == 1, "mysql-session-drop-unregistered-targets", response)
    policy = ("temporary" if shape["temporaryOnly"] else "ordinary") + ("-optional" if shape["ifExists"] else "-required")
    return item["fixture"], matching[0], policy


def analyze_request(item, sql, current=True, complete=True):
    fixture = item["fixture"]
    declarations = []
    for schema in (SCHEMA, DECOY):
        name = f"{schema}.{NAME}"
        if schema != SCHEMA or fixture != "temporary-only-primary":
            declarations.append(f"CREATE TABLE {name} (n int)")
        if schema in session_names(fixture):
            declarations.append(f"CREATE TEMPORARY TABLE {name} (n int)")
    return {"dialect": "mysql", "release": item["release"], "sql": sql, "declarations": declarations, "complete": complete,
            **({"currentDatabase": SCHEMA} if current else {})}


def analyzed(tag):
    yield f"DROP TABLE {NAME} RESTRICT", case(tag, "shadow-primary", "primary", "ordinary-required")
    yield f"DROP TEMPORARY TABLE {NAME}", case(tag, "temporary-only-primary", "primary", "temporary-required")
    yield f"DROP TEMPORARY TABLE {SCHEMA}.{NAME}", case(tag, "shadow-primary", "primary", "temporary-required")
    yield f"DROP TEMPORARY TABLE IF EXISTS {SCHEMA}.{NAME}", case(tag, "shadow-decoy", "primary", "temporary-optional")
    yield f"DROP TABLE {SCHEMA}.{NAME}, {DECOY}.{NAME}", case(tag, "shadow-primary", "primary-first", "ordinary-required")
    yield f"DROP TEMPORARY TABLE {DECOY}.{NAME}, {SCHEMA}.{NAME}", case(tag, "shadow-both", "decoy-first", "temporary-required")
    yield f"DROP TEMPORARY TABLE IF EXISTS {NAME}", case(tag, "shadow-decoy", "primary", "temporary-optional")
    yield f"DROP TABLE IF EXISTS {DECOY}.{NAME} CASCADE", case(tag, "shadow-decoy", "decoy", "ordinary-optional")


def negatives(tag):
    known = case(tag, "shadow-primary", "primary", "ordinary-required")
    coordinate = known["drop"]["targets"][0]
    broken = [
        ([], {}, "DROP TABLE needs a nonempty target list."),
        ([coordinate, coordinate], {}, "DROP TABLE cannot repeat a target."),
        ([[SCHEMA, NAME, "absent"]], {}, "An absent DROP TABLE target requires IF EXISTS."),
        ([[SCHEMA, NAME, "table"]], {"temporaryOnly": True}, "DROP TEMPORARY TABLE requires session-local targets."),
        (["untyped"], {}, "DROP TABLE requires checked target identities."),
    ]
    for targets, flags, reason in broken:
        yield {**known, "drop": {**known["drop"], "targets": targets, **flags}}, DIAGNOSTIC, reason
    missing = case(tag, "shadow-decoy", "primary", "temporary-optional")
    yield analyze_request(missing, f"DROP TEMPORARY TABLE {NAME}"), SEMANTIC, "does not exist"
    yield analyze_request(missing, f"DROP TEMPORARY TABLE {NAME}", complete=False), DIAGNOSTIC, "DROP TABLE requires an ordinary table"
    yield analyze_request(missing, f"DROP TEMPORARY TABLE IF EXISTS {NAME}", current=False), DIAGNOSTIC,         "Optional DROP TABLE needs a known explicit schema."


def requests(_, tag, spec):
    for index, item in enumerate(finite_cases(tag, spec)):
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "mysql-session-drop-authored-size")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", analyze_request(item, sql), item, None
    pool = sorted(finite_ids(None, spec, tag))
    for seed in spec["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            fixture, form, policy = rng.choice(pool)
            item = case(tag, fixture, form, policy, rng.randint(0, 40))
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(negatives(tag))
    require(len(invalid) == len(spec["negative"]), "mysql-session-drop-negatives-size")
    for index, (item, exception, message) in enumerate(invalid):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def location(schema):
    return f"{schema}.{NAME}"


def relation(kind, value):
    return [kind, [None, value, value]]


def initial(fixture, variant):
    permanent = {
        location(SCHEMA): None if fixture == "temporary-only-primary" else relation("table", 20 + variant),
        location(DECOY): relation("table", 40 + variant),
    }
    session = {}
    if SCHEMA in session_names(fixture):
        session[location(SCHEMA)] = relation(SESSION, 120 + variant)
    if DECOY in session_names(fixture):
        session[location(DECOY)] = relation(SESSION, 140 + variant)
    return permanent, session


def snapshot(permanent, session, marker, committed):
    return {"visible": {**permanent, **session}, "permanent": permanent, "marker": marker, "committedMarker": committed}


def prediction(item):
    permanent, session = initial(item["fixture"], item["variant"])
    before = snapshot(dict(permanent), dict(session), [0], [0])
    diagnostics = []
    for schema, _, kind in item["drop"]["targets"]:
        name = location(schema)
        if kind == SESSION:
            del session[name]
        elif kind == "table":
            permanent[name] = None
        else:
            diagnostics.append(["Note", 1051, f"Unknown table '{name}'"])
    active = item["drop"]["temporaryOnly"]
    after = snapshot(permanent, session, [0, 7], [0] if active else [0, 7])
    restored = snapshot(permanent, session, [0], [0]) if active else after
    return {"status": "mysql-session-drop", "initial": before, "effect": ["dropped", 0], "diagnostics": diagnostics,
            "active": active, "after": after, "restored": restored}


def observation(raw):
    return raw


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"], "variant": item["variant"]}


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1",
            "mysql-session-drop-positive-rejected", response)
    require(item["drop"] == response["mysqlDrop"], ROLE_MISMATCH,
            {"sql": response["sql"], "expected": item["drop"], "observed": response["mysqlDrop"]})


def check_observation(item, actual, sql):
    expected = prediction(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "mysql-session-drop", "mysql-session-drop-observer-rejected", detail)
    require(actual == expected, "meaning-mismatch", detail)


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, env, prepared):
    same(env["initial"], prepared["initial"])
    same(snapshot(*initial("shadow-both", 0), [0], [0]), prepared["initial"])


def sample_forms(forms, response, _, item):
    fixture, form, policy = finite_id(None, response, item)
    kinds = [kind for _, _, kind in response["mysqlDrop"]["targets"]]
    forms[f"fixture/{fixture}"] += 1
    forms[f"form/{form}"] += 1
    forms[f"policy/{policy}"] += 1
    forms[f"mixed/{'table' in kinds and SESSION in kinds}"] += 1
    for kind in kinds:
        forms[f"kind/{kind}"] += 1


def required_forms(*_):
    spec, _ = specifications()
    names = {"fixture": "fixture", "form": "targets", "policy": "policy"}
    return {*(f"{key}/{value}" for key, axis in names.items() for value in spec["finite"][axis]),
            "kind/table", "kind/temporary-table", "kind/absent", "mixed/True", "mixed/False"}


def controls():
    item = case("mysql-8.4.7", "shadow-primary", "primary", "temporary-required")
    preferred = prediction(item)
    check_observation(item, preferred, "correct session-local removal")
    wrong = prediction(case("mysql-8.4.7", "shadow-primary", "primary", "ordinary-required"))
    try:
        check_observation(item, wrong, "incorrect implicit commit")
    except Failure as problem:
        same("meaning-mismatch", problem.signal)
        return {"wrong-transaction-effect": {"input": item, **problem.detail}}
    raise Failure("mysql-session-drop-missed-wrong-transaction")


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-session-drop-unexpected-fault-response", response)
    raise Failure(INVALID_PUBLISHED, {"sql": response["sql"], "expected": item["boundary"], "observed": response})


def fault_probe(item, response, db):
    if "boundary" in item:
        boundary(item, response)
    else:
        probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(witness, failure.detail)
        return
    raise Failure("mysql-session-drop-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/DropTable.php"
    assert_path = path.replace("DropTable.php", "DropTableLowering.php")
    primary = case(tag, "shadow-primary", "primary", "ordinary-required")
    absence, a_exception, a_message = list(negatives(tag))[2]
    wrong_kind, t_exception, t_message = list(negatives(tag))[3]
    return [
        RenderFault("drop-loses-temporary-policy", path, "$this->temporaryOnly ? 'TEMPORARY ' : ''", "''",
                    case(tag, "shadow-primary", "primary", "temporary-required"), "meaning-mismatch"),
        RenderFault("drop-loses-session-role", assert_path,
                    "$resolution->table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable",
                    "ExternalRelationKind::BaseTable", primary, ROLE_MISMATCH, analyze_request(primary, f"DROP TABLE {NAME}")),
        RenderFault("drop-redirects-session-target", path, "$names[] = $target->quoted();",
                    "$names[] = '`ir_sdrop_decoy`.`ir_sdrop_data`';", primary, "meaning-mismatch"),
        RenderFault("drop-erases-second-target", path, "implode(', ', $names)", "implode(', ', array_slice($names, 0, 1))",
                    case(tag, "shadow-primary", "primary-first", "ordinary-required"), "meaning-mismatch"),
        RenderFault("drop-admits-permanent-in-temporary-mode", path, "$target->kind === ExternalRelationKind::TemporaryTable", "true",
                    {"boundary": {"exception": t_exception, "message": t_message}}, INVALID_PUBLISHED, wrong_kind),
        RenderFault("drop-admits-unconditional-known-absence", path, "Check::input($ifExists, 'An absent DROP TABLE target requires IF EXISTS.');", "",
                    {"boundary": {"exception": a_exception, "message": a_message}}, INVALID_PUBLISHED, absence),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
