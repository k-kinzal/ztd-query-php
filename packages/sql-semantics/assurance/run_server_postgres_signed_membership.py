"""Check the constructed signed membership nodes and their native PostgreSQL effects."""

import json
import sys

import postgres_signed_membership_cases as laws
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_create_btree import observation, specifications as profiles
from server_result_cases import match, result_window
from sqlite_domain import RenderFault

LABEL = "postgres-signed-membership"
OBSERVER = "postgres_signed_membership_observer.py"
CONTRACT_NAME = "postgres-signed-membership-evidence-v1.json"
CONTRACT = "afacf1e6c7706ceede0c99a6cdedf537085715d2a1358d2095b17f3425ddf36a"
WIDTH = refusal("Signed membership requires an ordered list of one to 8190 alternatives.")
TYPE = refusal("A membership alternative must be a pure native signed operand.")
OCCURRENCE = refusal("A tested predicate slot must belong to the SELECT source.")
SHADOW = refusal("A membership predicate requires the pg_catalog comparison profile.")
ADMITTED = "postgres-membership-empty-admitted"
MEANING = "meaning-mismatch"
COMPARATOR_FAULTS = {"Accept a known comparator observation with a dropped duplicate row": "omitDuplicateRow"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-signed-membership-evidence-contract")
    scope = json.loads(path.read_text())
    reference = scope["reference"]
    fixed = TOOL / reference["file"]
    require(digest(fixed) == reference["sha256"], "postgres-signed-membership-reference")
    normative = json.loads(fixed.read_text())
    require(normative["finite"]["allowedExclusions"] == normative["exploration"]["allowedExclusions"] == [],
            "postgres-signed-membership-exclusions")
    return {**normative, **scope}, profiles()[1]


def finite_ids(_, spec, _tag):
    actual = list(laws.defined_coordinates(spec))
    independent = laws.coordinates()
    require(set(actual) == independent and len(independent) == len(actual) == spec["finite"]["coordinatesPerRoute"],
            "postgres-membership-finite-definition")
    return independent


def finite_id(_, reply, item):
    check_value(item, reply)
    source = "unit" if reply["source"] is None else "resolved-table"
    placement = "filter" if reply["where"] is not None else "projection"
    term = reply["where"] if placement == "filter" else reply["columns"][0][0]
    kind, flag, subject, choices = term
    same(laws.TERM, kind)
    physical = lambda leaf: laws.physical_operand(leaf, reply["source"])
    return source, placement, flag, physical(subject), tuple(map(physical, choices))


def analysis_finite_id(engine, reply, item, sql):
    same(laws.sql(item), sql)
    return finite_id(engine, reply, item)


def direct_finite_id(engine, reply, item, request):
    same(laws.direct(item), request)
    return finite_id(engine, reply, item)


def projection(tag, subject, choices, negated=False, source="unit", shadow=False):
    return laws.scenario(tag, source, "projection", laws.membership(negated, subject, choices), shadow=shadow)


def size_witnesses(tag):
    integer = lambda n: laws.operand("integer", n)
    for last in (laws.NULL, integer(2)):
        yield projection(tag, integer(2), [*([integer(17)] * 8188), laws.NULL, last])


def negatives(tag):
    integer = ["integer", "2"]
    yield laws.direct(projection(tag, integer, [])), WIDTH
    invalid = laws.direct(projection(tag, integer, [integer]))
    invalid["columns"][0][0][3] = {"wrong": integer}
    yield invalid, WIDTH
    yield laws.direct(projection(tag, integer, [["boolean", True]])), TYPE
    yield laws.direct(projection(tag, integer, [integer] * 8191)), WIDTH
    yield laws.full_heading_request(projection(tag, ["slot", 0], [integer])), OCCURRENCE
    yield laws.full_heading_request(projection(tag, integer, [["wide-slot", 0, 0]], source="resolved-table")), OCCURRENCE
    query = {"dialect": "postgres", "release": tag, "declarations": laws.declarations()}
    yield {**query, "sql": "SELECT (2 + 1) IN (2) AS test"}, refusal("A signed comparison operand requires a pure signed integer or NULL.")
    yield {**query, "sql": "SELECT TRUE IN (FALSE) AS test"}, refusal("A signed comparison operand must resolve to pg_catalog.int4, pg_catalog.int8 or NULL.")
    yield laws.analysis(projection(tag, integer, [integer], shadow=True)), SHADOW


def requests(_, tag, spec):
    for index, coordinate in enumerate(laws.defined_coordinates(spec)):
        item = laws.finite(tag, coordinate)
        yield f"finite/{index}", laws.analysis(item), item, None
        yield f"direct/{index}", laws.direct(item), item, None
    for index, item in enumerate(size_witnesses(tag)):
        yield f"maximum/{index}/analyze", laws.analysis(item), item, None
        yield f"maximum/{index}/direct", laws.direct(item), item, None
    for index, (request, guard) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, guard
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(laws.generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", laws.analysis(item), item, None
        for index, item in enumerate(laws.generated(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", laws.direct(item), item, None
    item = projection(tag, ["integer", "2"], [["integer", "2"]], shadow=True)
    yield "witness/shadowed-equality", laws.direct(item), item, None


def check_value(item, reply):
    require(reply["status"] == "constructed"
            and reply["profile"] == item["tag"] + "/utf8/standard-conforming-strings/native",
            "postgres-membership-positive-rejected", reply)
    expected = laws.graph(item)
    require(expected == {name: reply.get(name) for name in expected}, "postgres-membership-graph",
            {"expected": expected, "actual": reply})


def prepare(_, db):
    return db.request({"verifyFixture": True})


def check_preparation(_, db_environment, preparation):
    fields = [["a", "int4", 23], ["b", "int8", 20]]
    require(set(preparation) == set(laws.ROWS) | {"True", "False"} and db_environment["libpq"] > 0,
            "postgres-membership-fixture-record")
    for name, data in laws.ROWS.items():
        match(result_window(fields, list(map(list, data)), [], None), preparation[name], "fixture rows")
    for shadow in (False, True):
        same({"status": "rows", "fields": [["positive", "bool", 16], ["negative", "bool", 16]],
              "rows": [[not shadow, shadow]]}, preparation[str(shadow)])


def observer_request(item, command):
    return {"sql": command, "shadow": item["shadow"], "placement": item["placement"]}


def check_observation(item, response, command):
    expected, after = laws.expected(item)
    try:
        match(expected, response, command)
        require(("after" in response) == (after is not None), MEANING)
        if after is not None:
            fields = [["a", "int4", 23], ["b", "int8", 20]]
            match(result_window(fields, after, [], None), response["after"], command)
    except Failure as error:
        raise Failure(error.signal, {"sql": command, "observed": response, "difference": error.detail}) from error


def sample_forms(forms, reply, _, item):
    role = item["placement"]
    forms[f"role/{role}"] += 1
    if role == "projection":
        term = reply["columns"][0][0]
    elif role == "join":
        term = reply["source"]["on"]
    else:
        term = reply["where"]
    if term[0] != laws.TERM:
        forms[f"wrapper/{term[0]}"] += 1
        if term[0] == "int4-case":
            term = term[1][0][0]
        else:
            term = term[2]
    _, negated, subject, alternatives = term
    forms[f"negated/{negated}"] += 1
    forms[f"size/{len(alternatives)}"] += 1
    for value in [subject, *alternatives]:
        forms[f"leaf/{value[0]}"] += 1
        if value[0] in ("slot", "wide-slot"):
            forms[f"occurrence/{value[2] if len(value) == 3 else 0}"] += 1
        if value[0] == "wide-integer" and value[1] in ("-9223372036854775808", "9223372036854775807"):
            forms[f"bound/{value[1]}"] += 1


def sample_native_forms(forms, response, _, item):
    if item["placement"] in ("update", "delete"):
        forms[f"nativeChanged/{item['placement']}/{bool(response['rows'])}"] += 1
    if item["placement"] == "projection" and response["fields"][0][1] == "bool":
        for row in response["rows"]:
            forms[f"nativeTruth/{row[0]}"] += 1


def required_forms(*_):
    return ({f"role/{role}" for role in ("projection", "filter", "join", "update", "delete")}
            | {f"wrapper/{tag}" for tag in ("int4-case", "truth-test", "null-test", "logic")}
            | {f"leaf/{kind}" for kind in ("integer", "wide-integer", "null", "wide-null", "slot", "wide-slot")}
            | {f"size/{size}" for size in range(1, 13)}
            | {f"nativeChanged/{role}/{changed}" for role in ("update", "delete") for changed in (True, False)}
            | {f"nativeTruth/{state}" for state in (True, False, None)}
            | {"negated/True", "negated/False", "occurrence/0", "occurrence/1",
               "bound/-9223372036854775808", "bound/9223372036854775807"})


def controls():
    item = projection("control", ["integer", "2"], [["slot", 0]], source="resolved-table")
    expected, _ = laws.expected(item)
    rows = next(iter(expected["bands"]))["members"]
    native = {"status": "rows", "fields": expected["fields"], "rows": rows}
    check_observation(item, native, laws.sql(item))
    duplicate = next(row for row in rows if rows.count(row) > 1)
    altered = list(rows)
    altered.remove(duplicate)
    require(duplicate in altered, "postgres-membership-comparator-no-duplicate")
    try:
        check_observation(item, {**native, "rows": altered}, laws.sql(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"omitDuplicateRow": error.detail}
    raise Failure("postgres-membership-duplicate-fault-unobserved")


def fault_probe(item, reply, db):
    if item["term"][3]:
        probe(item, reply, db, sys.modules[__name__])
        return
    if rejects(reply, WIDTH):
        return
    check_value(item, reply)
    command = reply["sql"]
    measured = db.request(observer_request(item, command))
    same({"status": "rows", "fields": [["observed", "bool", 16]], "rows": [[False]]}, measured)
    raise Failure(ADMITTED, {"sql": command, "observed": measured})


def replay_fault(rule, record):
    witness, item = record["detail"], rule.item
    healthy, mutant = record["healthyResponse"], record["mutantResponse"]
    check_value(item, mutant)
    same(mutant["sql"], witness["sql"])
    if rule.signal == ADMITTED:
        require(rejects(healthy, WIDTH), ADMITTED)
        same({"status": "rows", "fields": [["observed", "bool", 16]], "rows": [[False]]}, witness["observed"])
        return
    check_value(item, healthy)
    try:
        check_observation(item, witness["observed"], witness["sql"])
    except Failure as error:
        same(MEANING, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-membership-fault-not-reproduced")


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    sql = ir + "SelectSql.php"
    two = ["integer", "2"]
    one = ["integer", "1"]
    empty = projection(tag, two, [])
    last = projection(tag, two, [one, two])
    unknown = projection(tag, two, [one, laws.NULL])
    shadow = projection(tag, two, [two], shadow=True)
    negative = projection(tag, two, [two], negated=True)
    identity = "return $test->negated ? '(NOT ' . $match . ')' : $match;"
    layout = "        $list = 'CAST(ARRAY[' . implode(', ', $alternatives) . '] AS pg_catalog.int8[])';"
    return [
        RenderFault("accept-empty-membership", ir + "Predicate/IntegerMembership.php", " && $alternatives !== []", "",
                    empty, ADMITTED, laws.direct(empty)),
        RenderFault("drop-final-membership-alternative", sql, layout, "        array_pop($alternatives);\n" + layout,
                    last, MEANING, laws.direct(last)),
        RenderFault("coalesce-membership-unknown", sql, identity, "return 'COALESCE(' . $match . ', FALSE)';",
                    unknown, MEANING, laws.direct(unknown)),
        RenderFault("omit-stock-membership-operator", sql, "' OPERATOR(pg_catalog.=) ANY ('", "' = ANY ('",
                    shadow, MEANING, laws.direct(shadow)),
        RenderFault("ignore-membership-negation", sql, identity, "return $match;", negative, MEANING, laws.direct(negative)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
