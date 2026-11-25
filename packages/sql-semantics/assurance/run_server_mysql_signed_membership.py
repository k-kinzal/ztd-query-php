"""Compare fixed MySQL signed memberships against native values and write effects."""

import json
import sys

import mysql_signed_membership_cases as laws
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_signed_range import specifications as signed_profiles
from server_result_cases import match
from sqlite_domain import RenderFault

LABEL = "mysql-signed-membership"
OBSERVER = "mysql_signed_membership_observer.php"
CONTRACT_NAME = "mysql-signed-membership-evidence-v1.json"
CONTRACT = "7690a15d3d4d3520375e08b4275a04693011c92a10b2cb82baeca7e4cd896bd4"
MEANING = "meaning-mismatch"
ADMIT_EMPTY = "mysql-signed-membership-empty-admitted"
COMPARATOR_FAULTS = {"Ignore a known output type difference in the comparator": "lostTruthType"}


def refusal(message):
    return {"message": message, "exception": "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"}


WIDTH = refusal("Signed membership requires an ordered list of one to 8190 alternatives.")
TYPE = refusal("A membership alternative must be a pure signed integer or NULL.")
SCOPE = refusal("A predicate slot must belong to the SELECT source.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-signed-membership-contract")
    evidence = json.loads(path.read_text())
    reference = evidence["reference"]
    file = TOOL / reference["file"]
    require(digest(file) == reference["sha256"], "mysql-signed-membership-normative-contract")
    scope = json.loads(file.read_text())
    require(scope["finite"]["allowedExclusions"] == scope["exploration"]["allowedExclusions"] == []
            and set(scope["faults"]) - set(evidence["faults"]) == {"Keep a mutable caller array reference"}
            and evidence["sourceFaults"] == ["mysql-membership-use-caller-alternative-array"],
            "mysql-signed-membership-coverage-contract")
    return {**scope, **evidence}, signed_profiles()[1]


def finite_ids(_, spec, _tag):
    generated = list(laws.grid(spec))
    independent = laws.independent_ids()
    require(len(generated) == len(independent) == spec["finite"]["coordinatesPerRoute"]
            and set(generated) == independent, "mysql-signed-membership-finite-definition")
    return independent


def finite_id(_, response, item):
    check_value(item, response)
    source = "unit" if response["source"] is None else "five-widths"
    role = "filter" if response["where"] is not None else "projection"
    term = response["where"] if role == "filter" else response["columns"][0][0]
    _, negated, probe_term, choices = term
    physical = lambda leaf: laws.observed_physical(leaf, response["source"])
    return source, role, negated, physical(probe_term), tuple(map(physical, choices))


def analysis_finite_id(engine, response, item, command):
    same(laws.sql(item), command)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(laws.direct(item), request)
    return finite_id(engine, response, item)


def projection(tag, subject, alternatives, negated=False, source="unit"):
    return laws.case(tag, source, "projection", laws.membership(negated, subject, alternatives))


def maximum(tag):
    for last in (laws.literal(11), laws.literal(2)):
        yield projection(tag, laws.literal(2), [*([laws.literal(17)] * 8188), laws.NULL, last])


def negatives(tag):
    one = laws.literal(1)
    yield laws.direct(projection(tag, one, [])), WIDTH
    associative = laws.direct(projection(tag, one, [one]))
    associative["columns"][0][0][3] = {"alternate": one}
    yield associative, WIDTH
    yield laws.direct(projection(tag, one, [["boolean", True]])), TYPE
    yield laws.direct(projection(tag, one, [one] * 8191)), WIDTH
    invisible = laws.direct(projection(tag, one, [one]))
    invisible["columns"][0][0][3] = [["integral-slot", 0, 2]]
    yield invisible, SCOPE
    for slot, wrong in ((laws.INT, ["integral-slot", 0, 0]), (0, ["slot", 0])):
        source = laws.direct(projection(tag, one, [one], source="five-widths"))
        source["source"] = laws.heading(laws.LEFT, [slot])
        source["columns"][0][0][3] = [wrong]
        yield source, SCOPE
    written = {"dialect": "mysql", "release": tag, "currentDatabase": laws.DATABASE, "observeExactIntegralSlots": True}
    unsigned = "CREATE TABLE ir_fixture.unsigned_probe (n BIGINT UNSIGNED)"
    yield {**written, "sql": "SELECT n IN (1) AS outcome FROM unsigned_probe", "declarations": [unsigned]}, refusal(
        "A membership column requires signed integral storage.")
    yield {**written, "sql": "SELECT 9223372036854775808 IN (1) AS outcome"}, refusal(
        "Range integer is outside the signed 64-bit range.")
    yield {**written, "sql": "SELECT (2 + 1) IN (1) AS outcome"}, refusal(
        "A membership operand requires a pure signed integer or NULL.")


def requests(_, tag, spec):
    for number, coordinate in enumerate(laws.grid(spec)):
        item = laws.finite(tag, coordinate)
        yield f"finite/{number}", laws.analysis(item), item, None
        yield f"direct/{number}", laws.direct(item), item, None
    for number, item in enumerate(maximum(tag)):
        yield f"maximum/{number}/analyze", laws.analysis(item), item, None
        yield f"maximum/{number}/direct", laws.direct(item), item, None
    for number, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{number}", request, None, reason
    variation = spec["exploration"]
    for seed in variation["seeds"]:
        for number, item in enumerate(laws.generated(tag, seed, variation["casesPerSeed"])):
            yield f"seed/{seed}/{number}", laws.analysis(item), item, None
        for number, item in enumerate(laws.generated(tag, seed + 1, variation["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{number}", laws.direct(item), item, None


def check_value(item, response):
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native"
    if item["placement"] not in ("update", "delete"):
        profile += "/materialized" if item["tag"] == "mysql-5.6.51" else "/derived-merge=on"
    require(response["status"] == "constructed" and response["profile"] == profile, "mysql-signed-membership-positive", response)
    expected = laws.graph(item)
    require(expected == {key: response.get(key) for key in expected}, "mysql-signed-membership-graph",
            {"expected": expected, "observed": response})


def prepare(_, db):
    commands = []
    for name, rows in laws.RECORDS.items():
        target = laws.qualified(name)
        values = ", ".join("(" + ", ".join("NULL" if item is None else str(item) for item in row) + ")" for row in rows)
        commands.extend([f"DROP TABLE IF EXISTS {target}", laws.ddl(name), f"INSERT INTO {target} VALUES {values}"])
    return db.request({"fixture": commands})


def check_preparation(_, environment, fixture):
    require(environment["database"][2:] == ["", "utf8mb4", "utf8mb4"], "mysql-signed-membership-profile")
    for table, key in ((laws.LEFT, "table"), (laws.RIGHT, "peer")):
        match(laws.fixture_prediction(table), fixture[key], "fixture rows")
        same(0, fixture[key]["warnings"])
    same({"status": "rows", "fields": [["yes", "LONGLONG"], ["no", "LONGLONG"], ["unknown_value", "LONGLONG"]],
          "rows": [[1, 0, None]], "warnings": 0}, fixture["truth"])
    widths = [[table, name, width.lower(), "YES" if nullable else "NO"]
              for table in laws.RECORDS for name, width, nullable in zip(laws.NAMES, laws.WIDTHS, laws.NULLABILITY[table], strict=True)]
    same(widths, fixture["widths"])


def observer_request(item, command):
    return {"placement": item["placement"], "sql": command}


def observation(value):
    return value


def check_observation(item, value, command):
    expected = laws.expected(item)
    detail = {"sql": command, "expected": expected, "observed": value}
    role = item["placement"]
    require(value["status"] == (role if role in ("update", "delete") else "rows"), "mysql-signed-membership-native-refusal", detail)
    try:
        require(value["warnings"] == 0, MEANING, detail)
        if role in ("update", "delete"):
            require(expected["count"] == value["count"] and value["after"]["warnings"] == value["restored"]["warnings"] == 0,
                    MEANING, detail)
            match(expected["after"], value["after"], command)
            match(laws.fixture_prediction(laws.LEFT), value["restored"], command)
        else:
            match(expected, value, command)
    except Failure as error:
        raise Failure(error.signal, detail) from error


def sample_forms(forms, response, _, item):
    role = item["placement"]
    forms[f"placement/{role}"] += 1
    source = response.get("source", response.get("target"))
    scopes = [source["left"], source["right"]] if role == "join" else ([] if source is None else [source])
    for scope in scopes:
        for value in scope.get("columnTypes", ["int"] * len(scope["columns"])):
            width, nullable = value[1:] if isinstance(value, list) else ("INT", value == "int")
            forms[f"width/{width}/{nullable}"] += 1
    term = response["columns"][0][0] if role == "projection" else source["on"] if role == "join" else response["where"]
    if term[0] != laws.TERM:
        forms[f"wrapper/{term[0]}"] += 1
        term = term[2]
    forms[f"polarity/{term[1]}"] += 1
    forms[f"length/{len(term[3])}"] += 1
    for node in [term[2], *term[3]]:
        forms[f"leaf/{node[0]}"] += 1
        if node[0] == "range-integer" and node[2]:
            forms[f"padding/{node[3]}"] += 1
        if node[0] == "range-integer" and node[1] in ("-9223372036854775808", "9223372036854775807"):
            forms[f"extreme/{node[1]}"] += 1


def sample_native_forms(forms, response, _, item):
    role = item["placement"]
    if role in ("update", "delete"):
        forms[f"effects/{role}/{bool(response['count'])}"] += 1
    elif role == "projection":
        for row in response["rows"]:
            forms[f"truth/{row[0]}"] += 1


def required_forms(*_):
    return ({f"placement/{role}" for role in ("projection", "filter", "join", "update", "delete")}
            | {f"width/{width}/{flag}" for index, width in enumerate(laws.WIDTHS)
               for flag in {rest[index] for rest in laws.NULLABILITY.values()}}
            | {f"length/{length}" for length in range(1, 13)}
            | {f"effects/{role}/{changed}" for role in ("update", "delete") for changed in (True, False)}
            | {"polarity/True", "polarity/False", "wrapper/null-test", "wrapper/logic", "leaf/null", "leaf/range-integer",
               "leaf/slot", "leaf/integral-slot", "padding/True", "padding/False", "truth/1", "truth/0", "truth/None",
               "extreme/-9223372036854775808", "extreme/9223372036854775807"})


def controls():
    item = projection("control", laws.literal(2), [laws.literal(2)])
    expected = {"status": "rows", "fields": [["decision", "LONGLONG"]], "rows": [[1]], "warnings": 0}
    check_observation(item, expected, laws.sql(item))
    wrong = {**expected, "fields": [["decision", "LONG"]]}
    try:
        check_observation(item, wrong, laws.sql(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"lostTruthType": error.detail}
    raise Failure("mysql-signed-membership-type-control-accepted")


def fault_probe(item, response, db):
    if item["term"][3]:
        probe(item, response, db, sys.modules[__name__])
        return
    if rejects(response, WIDTH):
        return
    check_value(item, response)
    command = response["sql"]
    native = db.request(observer_request(item, command))
    require(native["status"] == "mysql-membership-native-error" and native["sqlState"] == "42000",
            "mysql-signed-membership-unrelated-empty-result", native)
    raise Failure(ADMIT_EMPTY, {"sql": command, "observed": native})


def replay_fault(rule, record):
    item, witness = rule.item, record["detail"]
    healthy, mutant = record["healthyResponse"], record["mutantResponse"]
    check_value(item, mutant)
    same(mutant["sql"], witness["sql"])
    if rule.signal == ADMIT_EMPTY:
        require(rejects(healthy, WIDTH) and witness["observed"]["status"] == "mysql-membership-native-error"
                and witness["observed"]["sqlState"] == "42000", ADMIT_EMPTY)
        return
    check_value(item, healthy)
    try:
        check_observation(item, witness["observed"], witness["sql"])
    except Failure as error:
        same(MEANING, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-membership-fault-not-reproduced")


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/"
    emitter = root + "SelectSql.php"
    two, one = laws.literal(2), laws.literal(1)
    empty = projection(tag, two, [])
    final = projection(tag, two, [one, two])
    polarity = projection(tag, two, [two], True)
    binding = projection(tag, laws.reference(0), [laws.reference(1), two], source="five-widths")
    output = "return '(' . $subject . ($test->negated ? ' NOT IN (' : ' IN (') . implode(', ', $alternatives) . '))';"
    return [
        RenderFault("admit-empty-signed-membership", root + "Predicate/IntegerMembership.php", " && $alternatives !== []", "",
                    empty, ADMIT_EMPTY, laws.direct(empty)),
        RenderFault("drop-final-member", emitter, output, "array_pop($alternatives);\n        " + output,
                    final, MEANING, laws.direct(final)),
        RenderFault("drop-membership-polarity", emitter, "($test->negated ? ' NOT IN (' : ' IN (')", "' IN ('",
                    polarity, MEANING, laws.direct(polarity)),
        RenderFault("choose-next-native-width", emitter,
                    "$this->rangeOperand($test->subject, $source, $offset, $qualify)",
                    "$this->rangeOperand(new IntegralColumnReference($test->subject->slot + 1), $source, $offset, $qualify)",
                    binding, MEANING, laws.direct(binding)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
