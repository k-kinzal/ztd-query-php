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
CONTRACT_NAME = "mysql-truncate-v3.json"
CONTRACT = "dd0846981c177b548590b345ae043fc2238b28c4c7a2cdab70d707486a760d74"
DEPENDENCIES = "360b2331f177deb6e7e060341d3441d3dab037f874836b8debd3cc65149ee57c"
PREVIOUS_CONTRACT = "33bda84cb25fd77b42926291742bfd32a3b66616db27c65e00cdc4b3c8406dd4"
OBSERVER_CONTRACT = "5d9d281f54a92ce6f015c0f8e776081522d613a9a9140cb081ea34d6ed75ba2f"
COMPLETE_SEEDS = True
SCHEMA, DECOY = "ir_fixture", "ir_mtr_decoy"
A, UPPER, B, ESCAPED, REF, ABSENT = ("ir_mtr_" + name for name in ("a", "A", "b", "`quote", "ref", "absent"))
AXES = ("fixture", "target", "kind")
SESSION = "temporary-table"
NAME_FAILURE = "An absolute MySQL table name needs 1 to 64 characters without path separators or a trailing space."
BIND_FAILURE = "TRUNCATE TABLE requires a resolved base table."
KIND_FAILURE = "A resolved table identity requires a persistent or temporary table."
DEPENDENCY_FAILURE = "TRUNCATE TABLE is blocked by a foreign key from another table."
OPEN_CATALOG = "A persistent truncation needs a complete catalog of referencing tables."
ROLE_MISMATCH = "mysql-truncate-target-role"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-truncate-v1.json") == PREVIOUS_CONTRACT, "mysql-truncate-contract-mismatch")
    require(digest(TOOL / "mysql-truncate-observer-v1.json") == OBSERVER_CONTRACT,
            "mysql-truncate-observer-contract-mismatch")
    dependencies = TOOL / "mysql-truncate-foreign-dependencies-v2.json"
    require(digest(dependencies) == DEPENDENCIES, "mysql-truncate-dependency-contract-digest")
    definition = json.loads(path.read_text())
    inherited = TOOL / "mysql-truncate-v2.json"
    require(digest(inherited) == "ae66fbc18e9d2fc59120550865194a38c82d6f6ad0b1da1f320988562e76414d",
            "mysql-truncate-legacy-contract-digest")
    same(json.loads(inherited.read_text())["exploration"], definition["exploration"])
    return definition, profiles()[1]


def targets():
    return {
        "primary": [SCHEMA, A],
        "secondary": [SCHEMA, B],
        "decoy": [DECOY, A],
        "mixed-case": [SCHEMA, UPPER],
        "escaped": [SCHEMA, ESCAPED],
        "absent": [SCHEMA, ABSENT],
    }


def case(tag, fixture, target, variant=0, kind="table"):
    referrers = []
    if kind == "table" and target == [SCHEMA, A]:
        if fixture.startswith("foreign-key-"):
            referrers.append([SCHEMA, REF, "table"])
        if fixture == "self-reference":
            referrers.append([SCHEMA, A, "table"])
    return {"dialect": "mysql", "release": tag, "fixture": fixture, "variant": variant,
            "mysqlTruncate": [*target, kind], "referrers": referrers}


def finite_ids(_, spec, _tag):
    finite = spec["finite"]
    expected = set(product(*(finite[axis] for axis in AXES)))
    require(len(expected) == finite["identities"], "mysql-truncate-grid-size")
    admitted = {point for point in expected if unavailable(case(_tag, point[0], targets()[point[1]], kind=point[2])) is None}
    same(finite["admitted"], len(admitted))
    return admitted


def finite_id(_, reply, item):
    identity = reply["mysqlTruncate"]
    names = [role for role, target in targets().items() if target == identity[:2]]
    require(len(names) == 1, "mysql-truncate-unregistered-identity", reply)
    return item["fixture"], names[0], identity[2]


def quote(identifier):
    return "`" + identifier.replace("`", "``") + "`"


def qualified(target):
    return ".".join(map(quote, target))


def analyzed_request(tag, sql, declarations=None, complete=True, temporary=None):
    if declarations is None:
        declarations = [f"CREATE TABLE {qualified(target)} (id int)" for role, target in targets().items() if role != "absent"]
    if temporary is not None:
        declarations = [*declarations, f"CREATE TEMPORARY TABLE {qualified(temporary)} (id int)"]
    return {"dialect": "mysql", "release": tag, "currentDatabase": SCHEMA, "declarations": declarations, "sql": sql, "complete": complete}


def declarations(item):
    fixture = item["fixture"]
    initial_tables = []
    for label, name in targets().items():
        if label == "absent":
            continue
        self_reference = fixture == "self-reference" and label == "primary"
        clause = ", FOREIGN KEY (parent_id) REFERENCES " + qualified(name) + "(id)" if self_reference else ""
        initial_tables.append(f"CREATE TABLE {qualified(name)} (id INT PRIMARY KEY, parent_id INT{clause})")
    if fixture.startswith("foreign-key-"):
        child = qualified([SCHEMA, REF])
        parent = qualified([SCHEMA, A])
        initial_tables.append(f"CREATE TABLE {child} (id INT PRIMARY KEY, parent_id INT, FOREIGN KEY (parent_id) REFERENCES {parent}(id))")
    return initial_tables


def analyzed(tag):
    names = targets()
    yield f"TRUNCATE {A}", case(tag, "plain", names["primary"])
    yield f"TRUNCATE TABLE {SCHEMA}.{A}", case(tag, "foreign-key-filled", names["primary"])
    yield f"TRUNCATE TABLE {DECOY}.{A}", case(tag, "foreign-key-empty", names["decoy"])
    yield f"TRUNCATE {UPPER}", case(tag, "plain", names["mixed-case"])
    yield f"TRUNCATE TABLE {qualified(names['escaped'])}", case(tag, "plain", names["escaped"])
    yield f"TRUNCATE TABLE {A}", case(tag, "self-reference", names["primary"])
    yield f"TRUNCATE {A}", case(tag, "plain", names["primary"], kind=SESSION)
    yield f"TRUNCATE TABLE {SCHEMA}.{A}", case(tag, "foreign-key-filled", names["primary"], kind=SESSION)
    yield f"TRUNCATE {ABSENT}", case(tag, "plain", names["absent"], kind=SESSION)
    yield f"TRUNCATE TABLE {DECOY}.{A}", case(tag, "foreign-key-empty", names["decoy"], kind=SESSION)
    yield f"TRUNCATE {UPPER}", case(tag, "plain", names["mixed-case"], kind=SESSION)
    yield f"TRUNCATE TABLE {qualified(names['escaped'])}", case(tag, "plain", names["escaped"], kind=SESSION)


def negatives(tag):
    yield case(tag, "plain", [SCHEMA, "bad/name"]), DIAGNOSTIC, NAME_FAILURE
    yield case(tag, "plain", [SCHEMA + " ", A]), DIAGNOSTIC, NAME_FAILURE
    yield analyzed_request(tag, f"TRUNCATE {ABSENT}"), SEMANTIC, "does not exist"
    yield analyzed_request(tag, f"TRUNCATE {ABSENT}", complete=False), DIAGNOSTIC, BIND_FAILURE
    yield analyzed_request(tag, f"TRUNCATE {A}", [f"CREATE VIEW {SCHEMA}.{A} AS SELECT 1 AS id"]), SEMANTIC, "is a view"
    yield analyzed_request(tag, f"TRUNCATE {A}", complete=False), DIAGNOSTIC, OPEN_CATALOG
    yield case(tag, "plain", targets()["primary"], kind="view"), DIAGNOSTIC, KIND_FAILURE


def seeded(tag, spec, seed, count):
    rng = Random(seed)
    for position in range(count):
        fixture = rng.choice(spec["finite"]["fixture"])
        target = targets()[rng.choice(spec["finite"]["target"])]
        variant = rng.randint(1, 99)
        kind = rng.choice(spec["finite"]["kind"])
        yield case(tag, fixture, target, 0 if position == 0 else variant, kind)


def unavailable(item):
    effect = prediction(item)["effect"]
    return {1146: BIND_FAILURE, 1701: DEPENDENCY_FAILURE}.get(effect[-1])


def direct_request(item):
    return {**item, "mysqlTruncate": None} if unavailable(item) == BIND_FAILURE else item


def rejection(name, item, wire, boundary):
    return "negative/" + name, wire, {**item, "rejectionId": name}, {"exception": DIAGNOSTIC, "message": boundary}


def native_refusals(tag, spec):
    for index, (fixture, target, kind) in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = case(tag, fixture, targets()[target], kind=kind)
        message = unavailable(item)
        if message is not None:
            yield rejection(f"finite/{index}", item, direct_request(item), message)
    for index, (sql, item) in enumerate(analyzed(tag)):
        message = unavailable(item)
        if message is not None:
            wire = analyzed_request(tag, sql, declarations(item), temporary=session_target(item))
            yield rejection(f"analyze/{index}", item, wire, message)
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, spec, seed, spec["exploration"]["casesPerSeed"])):
            message = unavailable(item)
            if message is not None:
                yield rejection(f"seed/{seed}/{index}", item, direct_request(item), message)


def requests(engine, tag, spec):
    for index, (fixture, target, kind) in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = case(tag, fixture, targets()[target], kind=kind)
        if unavailable(item) is None:
            yield f"finite/{index}", item, item, None
    written = list(analyzed(tag))
    require(len(written) == len(spec["analysis"]), "mysql-truncate-analysis-count")
    for index, (sql, item) in enumerate(written):
        if unavailable(item) is None:
            yield f"analyze/{index}", analyzed_request(tag, sql, declarations(item), temporary=session_target(item)), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, spec, seed, spec["exploration"]["casesPerSeed"])):
            if unavailable(item) is not None:
                item = case(tag, item["fixture"], item["mysqlTruncate"][:2], item["variant"], SESSION)
            yield f"seed/{seed}/{index}", item, item, None
    observed = list(native_refusals(tag, spec))
    same(spec["refusalsWithNativeEvidence"], len(observed))
    yield from observed
    bad = list(negatives(tag))
    require(len(bad) == len(spec["negative"]), "mysql-truncate-negatives-count")
    same(spec["negativeCount"], len(bad) + len(observed))
    for index, (item, exception, message) in enumerate(bad):
        yield f"negative/local/{index}", item, None, {"exception": exception, "message": message}


def rejection_sql(item, _request):
    return "TRUNCATE TABLE " + qualified(item["mysqlTruncate"][:2])


def observe_rejection(_, item, actual, sql):
    require(unavailable(item) is not None, "mysql-truncate-expected-native-refusal", item)
    check_observation(item, observation(actual), sql)
    return item["rejectionId"]


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, _ in native_refusals(tag, spec)}


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


def session_target(item):
    scope = item["mysqlTruncate"]
    return scope[:2] if scope[2] == SESSION else None


def temporary_state(variant, full):
    rows = [[101, f"session:{variant}"], [102, f"session:{variant}"], [103, None]] if full else []
    return {"temporary": True, "fields": [["id", "LONG"], ["label", "VAR_STRING"]], "rows": rows}


def prediction(item):
    previous = initial(item["fixture"], item["variant"])
    target = coordinate(item["mysqlTruncate"][:2])
    session = session_target(item) is not None
    relations = dict(previous["relations"])
    restricted = any(name != target and any(coordinate(key[2:4]) == target for key in relation["references"])
                     for name, relation in relations.items())
    if session:
        effect = ["executed", 0]
    elif target not in relations:
        effect = ["db-error", "42S02", 1146]
    elif restricted:
        effect = ["db-error", "42000", 1701]
    else:
        effect = ["executed", 0]
        relations[target] = {**relations[target], "rows": [], "nextId": 1}
    after = {**previous, "relations": relations, "marker": [0, 7]}
    temporary = {
        "sessionBefore": temporary_state(item["variant"], True) if session else None,
        "sessionAfter": temporary_state(item["variant"], False) if session else None,
        "sessionRestored": temporary_state(item["variant"], False) if session else None,
        "sessionAllocated": [1, 1] if session else None,
    }
    return {"status": "mysql-truncate-effect", "settings": [SCHEMA, 1, 1, 1], "before": previous,
            "effect": effect, "active": False, "after": after, "afterRollback": after,
            "allocated": {name: relation["nextId"] for name, relation in relations.items()}, "session": temporary}


def normalize(state):
    return {**state, "relations": {name: {**relation, "rows": sorted(relation["rows"], key=json.dumps)}
                                  for name, relation in state["relations"].items()}}


def observation(raw):
    if raw["status"] != "mysql-truncate-effect":
        return raw
    observed_session = {name: ({**entry, "rows": sorted(entry["rows"], key=json.dumps)}
                               if isinstance(entry, dict) else entry) for name, entry in raw["session"].items()}
    return {**raw, **{phase: normalize(raw[phase]) for phase in ("before", "after", "afterRollback")}, "session": observed_session}


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1",
            "mysql-truncate-positive-rejected", response)
    require(item["mysqlTruncate"] == response["mysqlTruncate"], ROLE_MISMATCH,
            {"sql": response["sql"], "expected": item["mysqlTruncate"], "observed": response["mysqlTruncate"]})


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"], "variant": item["variant"], "sessionTarget": session_target(item)}


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
    fixture, target, kind = finite_id(None, reply, item)
    forms[f"fixture/{fixture}"] += 1
    forms[f"target/{target}"] += 1
    forms[f"kind/{kind}"] += 1
    forms["data/varied" if item["variant"] else "data/base"] += 1


def required_forms(*_):
    spec, _ = specifications()
    return {*(f"fixture/{kind}" for kind in spec["finite"]["fixture"]), *(f"target/{kind}" for kind in targets()),
            *(f"kind/{kind}" for kind in spec["finite"]["kind"]), "data/base", "data/varied"}


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
    invalid_kind, kind_exception, kind_message = list(negatives(tag))[-1]
    local = case(tag, "foreign-key-filled", targets()["primary"], kind=SESSION)
    local_request = analyzed_request(tag, f"TRUNCATE {A}", temporary=targets()["primary"])
    bad_path = 'strpbrk($identifier->value, \'/\\\\.\') === false && '
    blocked = case(tag, "foreign-key-filled", names["primary"])
    inbound_guard = """$child->kind === ExternalRelationKind::BaseTable
                && $child->name->schema->value === $target->name->schema->value
                && $child->name->table->value === $target->name->table->value"""
    return [
        RenderFault("truncate-publish-blocked-parent", f"{root}/Truncate/TruncationDependents.php", inbound_guard, "true",
                    {"boundary": {"exception": DIAGNOSTIC, "message": DEPENDENCY_FAILURE}}, "mysql-truncate-invalid-published", blocked),
        RenderFault("truncate-erase-schema", table, "$this->target->quoted()", "$this->target->name->table->quoted()",
                    case(tag, "plain", names["decoy"]), "meaning-mismatch"),
        RenderFault("truncate-redirect-to-decoy", table, "$this->target->quoted()", repr(qualified(names["decoy"])),
                    case(tag, "plain", names["primary"]), "meaning-mismatch"),
        RenderFault("truncate-fixed-valid-output", table, "'TRUNCATE TABLE ' . $this->target->quoted()",
                    repr("TRUNCATE TABLE " + qualified(names["secondary"])), case(tag, "self-reference", names["primary"]), "meaning-mismatch"),
        RenderFault("truncate-fold-case", table, "$this->target->quoted()", "strtolower($this->target->quoted())",
                    case(tag, "plain", names["mixed-case"]), "meaning-mismatch"),
        RenderFault("truncate-erase-session-role", f"{root}/Truncate/TruncateLowering.php",
                    "$resolution->table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable",
                    "ExternalRelationKind::BaseTable", local, ROLE_MISMATCH, local_request),
        RenderFault("truncate-admit-path-separator", f"{root}/TableName.php", bad_path, "",
                    {"boundary": {"exception": name_exception, "message": name_message}}, "mysql-truncate-invalid-published", name_input),
        RenderFault("truncate-admit-view-target", f"{root}/Identity/TableIdentity.php", "$kind->isTable()", "true",
                    {"boundary": {"exception": kind_exception, "message": kind_message}}, "mysql-truncate-invalid-published", invalid_kind),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
