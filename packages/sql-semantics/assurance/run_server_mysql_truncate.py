"""Compare native MySQL truncation with independently predicted fixture effects."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_ordered_table_rename import DIAGNOSTIC, SEMANTIC, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "mysql-truncate"
OBSERVER = "mysql_truncate_observer.php"
CONTRACT_NAME = "mysql-truncate-v1.json"
CONTRACT = "33bda84cb25fd77b42926291742bfd32a3b66616db27c65e00cdc4b3c8406dd4"
COMPLETE_SEEDS = True
SCHEMA, DECOY = "ir_fixture", "ir_mtr_decoy"
A, UPPER, B, ESCAPED, REF, ABSENT = ("ir_mtr_" + name for name in ("a", "A", "b", "`quote", "ref", "absent"))
AXES = ("fixture", "target")
NAME_FAILURE = "An absolute MySQL table name needs 1 to 64 characters without path separators or a trailing space."
BIND_FAILURE = "TRUNCATE TABLE requires a resolved persistent base table."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-truncate-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def targets():
    return {
        "primary": [SCHEMA, A],
        "secondary": [SCHEMA, B],
        "decoy": [DECOY, A],
        "mixed-case": [SCHEMA, UPPER],
        "escaped": [SCHEMA, ESCAPED],
        "absent": [SCHEMA, ABSENT],
    }


def case(tag, fixture, target, variant=0):
    return {"dialect": "mysql", "release": tag, "fixture": fixture, "variant": variant, "mysqlTruncate": target}


def finite_ids(_, spec, _tag):
    finite = spec["finite"]
    expected = set(product(*(finite[axis] for axis in AXES)))
    require(len(expected) == finite["identities"], "mysql-truncate-grid-size")
    return expected


def finite_id(_, reply, item):
    names = [role for role, target in targets().items() if target == reply["mysqlTruncate"]]
    require(len(names) == 1, "mysql-truncate-unregistered-identity", reply)
    return item["fixture"], names[0]


def quote(identifier):
    return "`" + identifier.replace("`", "``") + "`"


def qualified(target):
    return ".".join(map(quote, target))


def analyzed_request(tag, sql, declarations=None, complete=True):
    if declarations is None:
        declarations = [f"CREATE TABLE {qualified(target)} (id int)" for role, target in targets().items() if role != "absent"]
    return {"dialect": "mysql", "release": tag, "currentDatabase": SCHEMA, "declarations": declarations, "sql": sql, "complete": complete}


def analyzed(tag):
    names = targets()
    yield f"TRUNCATE {A}", case(tag, "plain", names["primary"])
    yield f"TRUNCATE TABLE {SCHEMA}.{A}", case(tag, "foreign-key-filled", names["primary"])
    yield f"TRUNCATE TABLE {DECOY}.{A}", case(tag, "foreign-key-empty", names["decoy"])
    yield f"TRUNCATE {UPPER}", case(tag, "plain", names["mixed-case"])
    yield f"TRUNCATE TABLE {qualified(names['escaped'])}", case(tag, "plain", names["escaped"])
    yield f"TRUNCATE TABLE {A}", case(tag, "self-reference", names["primary"])


def negatives(tag):
    yield case(tag, "plain", [SCHEMA, "bad/name"]), DIAGNOSTIC, NAME_FAILURE
    yield case(tag, "plain", [SCHEMA + " ", A]), DIAGNOSTIC, NAME_FAILURE
    yield analyzed_request(tag, f"TRUNCATE {ABSENT}"), SEMANTIC, "does not exist"
    yield analyzed_request(tag, f"TRUNCATE {ABSENT}", complete=False), DIAGNOSTIC, BIND_FAILURE
    yield analyzed_request(tag, f"TRUNCATE {A}", [f"CREATE VIEW {SCHEMA}.{A} AS SELECT 1 AS id"]), SEMANTIC, "is a view"
    yield analyzed_request(tag, f"TRUNCATE {A}", [f"CREATE TEMPORARY TABLE {SCHEMA}.{A} (id int)"]), DIAGNOSTIC, BIND_FAILURE


def seeded(tag, spec, seed, count):
    rng = Random(seed)
    for position in range(count):
        fixture = rng.choice(spec["finite"]["fixture"])
        target = targets()[rng.choice(spec["finite"]["target"])]
        variant = rng.randint(1, 99)
        yield case(tag, fixture, target, 0 if position == 0 else variant)


def requests(_, tag, spec):
    for index, (fixture, target) in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = case(tag, fixture, targets()[target])
        yield f"finite/{index}", item, item, None
    written = list(analyzed(tag))
    require(len(written) == len(spec["analysis"]), "mysql-truncate-analysis-count")
    for index, (sql, item) in enumerate(written):
        yield f"analyze/{index}", analyzed_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, spec, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    bad = list(negatives(tag))
    require(len(bad) == len(spec["negative"]), "mysql-truncate-negatives-count")
    for index, (item, exception, message) in enumerate(bad):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def coordinate(target):
    return ".".join(target)


def definition(width, reference, trigger, initial, rows):
    return {
        "fields": [["id", "int", "NO", None, None, "auto_increment"],
                   ["label", "varchar", "YES", width, "utf8mb4_bin", ""],
                   ["parent_id", "int", "YES", None, None, ""]],
        "indices": ([["parent_index", 1, 1, "parent_id"]] if reference else []) + [["PRIMARY", 0, 1, "id"]],
        "references": [["ir_mtr_parent_fk", "parent_id", SCHEMA, A, "id"]] if reference else [],
        "triggers": [["AFTER", "DELETE", f"ir_mtr_after_delete_{trigger}"]],
        "engine": "InnoDB", "nextId": initial + len(rows), "rows": sorted(rows, key=json.dumps),
    }


def initial(fixture, variant):
    primary = [SCHEMA, A]
    sources = [(primary, 11, 8), ([SCHEMA, UPPER], 21, 12), ([SCHEMA, B], 31, 13),
               ([SCHEMA, ESCAPED], 41, 16), ([DECOY, A], 81, 14)]
    if fixture.startswith("foreign-key-"):
        sources.append(([SCHEMA, REF], 91, 9))
    result = {}
    for trigger, (target, start, width) in enumerate(sources):
        label = f"{start}:{variant}"
        child = target == [SCHEMA, REF]
        self_reference = target == primary and fixture == "self-reference"
        rows = [[start, label, 11 if child else None]]
        if target == primary:
            rows.extend([[start + 1, label, 11 if self_reference else None], [start + 2, None, None]])
        if child and fixture == "foreign-key-empty":
            rows.clear()
        result[coordinate(target)] = definition(width, child or self_reference, trigger, start, rows)
    return {"relations": result, "marker": [0], "deleteEvents": []}


def prediction(item):
    previous = initial(item["fixture"], item["variant"])
    target = coordinate(item["mysqlTruncate"])
    relations = dict(previous["relations"])
    restricted = any(name != target and any(coordinate(key[2:4]) == target for key in relation["references"])
                     for name, relation in relations.items())
    if target not in relations:
        effect = ["db-error", "42S02", 1146]
    elif restricted:
        effect = ["db-error", "42000", 1701]
    else:
        effect = ["executed", 0]
        relations[target] = {**relations[target], "rows": [], "nextId": 1}
    after = {**previous, "relations": relations, "marker": [0, 7]}
    return {"status": "mysql-truncate-effect", "settings": [SCHEMA, 1, 1, 1], "before": previous,
            "effect": effect, "active": False, "after": after, "afterRollback": after,
            "allocated": {name: relation["nextId"] for name, relation in relations.items()}}


def normalize(state):
    return {**state, "relations": {name: {**relation, "rows": sorted(relation["rows"], key=json.dumps)}
                                  for name, relation in state["relations"].items()}}


def observation(raw):
    if raw["status"] != "mysql-truncate-effect":
        return raw
    return {**raw, **{phase: normalize(raw[phase]) for phase in ("before", "after", "afterRollback")}}


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native",
            "mysql-truncate-positive-rejected", response)
    same(item["mysqlTruncate"], response["mysqlTruncate"])


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"], "variant": item["variant"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(environment["initial"], preparation["initial"])
    same(initial("plain", 0), normalize(environment["initial"]))


def check_observation(item, actual, sql):
    wanted = prediction(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "mysql-truncate-effect", "mysql-truncate-observer-rejected", witness)
    try:
        same(wanted, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    fixture, target = finite_id(None, reply, item)
    forms[f"fixture/{fixture}"] += 1
    forms[f"target/{target}"] += 1
    forms["data/varied" if item["variant"] else "data/base"] += 1


def required_forms(*_):
    spec, _ = specifications()
    return {*(f"fixture/{kind}" for kind in spec["finite"]["fixture"]), *(f"target/{kind}" for kind in targets()),
            "data/base", "data/varied"}


def controls():
    item = case("mysql-8.4.7", "plain", targets()["primary"])
    correct = prediction(item)
    check_observation(item, correct, "correct allocation reset")
    wrong = {**correct, "allocated": {**correct["allocated"], coordinate(targets()["primary"]): 14}}
    try:
        check_observation(item, wrong, "next value was retained")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"unreset-next-id": {"input": item, **error.detail}}
    raise Failure("mysql-truncate-comparator-missed-allocation")


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-truncate-mutant-response", response)
    raise Failure("mysql-truncate-invalid-published", {"sql": response["sql"], "observed": response, "expected": item["boundary"]})


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
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-truncate-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Schema"
    table = f"{root}/Truncate/TruncateTable.php"
    names = targets()
    name_input, name_exception, name_message = list(negatives(tag))[0]
    bound_input, bound_exception, bound_message = list(negatives(tag))[-1]
    bad_path = 'strpbrk($identifier->value, \'/\\\\.\') === false && '
    return [
        RenderFault("truncate-erase-schema", table, "$this->target->quoted()", "$this->target->table->quoted()",
                    case(tag, "plain", names["decoy"]), "meaning-mismatch"),
        RenderFault("truncate-redirect-to-decoy", table, "$this->target->quoted()", repr(qualified(names["decoy"])),
                    case(tag, "plain", names["primary"]), "meaning-mismatch"),
        RenderFault("truncate-fixed-valid-output", table, "'TRUNCATE TABLE ' . $this->target->quoted()",
                    repr("TRUNCATE TABLE " + qualified(names["secondary"])), case(tag, "self-reference", names["primary"]), "meaning-mismatch"),
        RenderFault("truncate-fold-case", table, "$this->target->quoted()", "strtolower($this->target->quoted())",
                    case(tag, "plain", names["mixed-case"]), "meaning-mismatch"),
        RenderFault("truncate-admit-temporary", f"{root}/Truncate/TruncateLowering.php", "!$resolution->table->temporary && ", "",
                    {"boundary": {"exception": bound_exception, "message": bound_message}}, "mysql-truncate-invalid-published", bound_input),
        RenderFault("truncate-admit-path-separator", f"{root}/TableName.php", bad_path, "",
                    {"boundary": {"exception": name_exception, "message": name_message}}, "mysql-truncate-invalid-published", name_input),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
