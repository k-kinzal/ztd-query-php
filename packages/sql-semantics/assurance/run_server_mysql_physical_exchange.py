"""Compare an atomic MySQL physical-key removal and foreign-key addition."""

from copy import deepcopy
from functools import lru_cache
import json
import re
import sys

import mysql_physical_exchange_cases as laws
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-physical-exchange"
NAME = "mysql-physical-exchange-evidence-v1.json"
CONTRACT = "34b9b120623d32f1f792686b357659dd931663daa825e948d4eb40e5911281fc"
OBSERVER = "mysql_physical_exchange_observer.php"
FINITE = "mysql-physical-exchange-finite-v1.json"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-foreign-keys"
COMPARATOR_FAULTS = {
    "Ignore the identity of a retained native constraint.": "change-retained-constraint",
    "Ignore the newly generated native value.": "change-generated-value",
}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT, "mysql-physical-exchange-contract")
    specification = json.loads(path.read_text())
    _, profiles = native_profiles()
    same(profiles["mysql"]["releases"], specification["releases"])
    for name, expected in specification["references"].items():
        require(digest(TOOL / name) == expected, "mysql-physical-exchange-reference", name)
    fixture(specification["references"][FINITE])
    return specification, profiles


@lru_cache(maxsize=1)
def fixture(finite_digest):
    path = TOOL / FINITE
    require(digest(path) == finite_digest, "mysql-physical-exchange-finite-catalog")
    cases = json.loads(path.read_text())["cases"]
    same(laws.coordinates(), [case["id"] for case in cases])
    return cases


def release_for_expected(tag):
    if tag in ("mysql-8.1.0", "mysql-8.2.0", "mysql-8.3.0"):
        return "mysql-8.0.44"
    if tag == "mysql-9.0.1":
        return "mysql-9.1.0"
    return tag


def all_cases(spec, tag):
    return fixture(spec["references"][FINITE])


def accepted(case, tag):
    return case["outcomes"][release_for_expected(tag)]["alter"] == "ok"


def finite_ids(_, spec, tag):
    result = {case["id"] for case in all_cases(spec, tag) if accepted(case, tag)}
    same(spec["admitted"][tag], len(result))
    return result


def rejected_finite_ids(spec, tag):
    return {f"finite/{case['id']}/{route}" for case in all_cases(spec, tag) if not accepted(case, tag)
            for route in ("analysis", "direct")}


def reason(case):
    family, source, *_ = case["id"].split("/")
    if family == "generating":
        return "Atomic removal must retain a backing key for the AUTO_INCREMENT field."
    if family == "self-parent":
        if source == "unindexed":
            return "A native foreign key requires original physical support on the resolved parent."
        return "The resulting physical keys must support the new self-referenced parent."
    return "The resulting physical keys must support every retained native foreign-key side."


def item(case, tag, identity=None):
    release = tag.removeprefix("mysql-")
    return {"case": case, "tag": tag, "expected": laws.expected_value(laws.direct(case, release)), "identity": identity}


def negatives(spec, tag):
    original = next(c for c in all_cases(spec, tag) if c["id"] == "outgoing/explicit/same-name/same-side/False/drop-first")
    packet = laws.direct(original, tag.removeprefix("mysql-"))
    for label, field, value in (
        ("wrong-owner", "owner", ["ir_fixture", "fk_test_root", "table"]),
        ("wrong-role", "role", "unique"),
        ("wrong-label", "name", "not_original"),
    ):
        changed = deepcopy(packet)
        changed["mysqlAlterForeignKeys"]["actions"][0]["physicalDrop"][field] = value
        yield label, changed, "A mixed physical removal needs one distinct original whole B-tree on the altered table."
    changed = deepcopy(packet)
    del changed["mysqlAlterForeignKeys"]["remainingSides"]
    yield "missing-layout", changed, "An explicit physical removal requires a complete retained foreign-key layout."
    changed = deepcopy(packet)
    changed["mysqlAlterForeignKeys"]["remainingSides"] = []
    yield "missing-retained-child", changed, "The supplied layout must cover each retained original child side."
    changed = deepcopy(packet)
    changed["mysqlAlterForeignKeys"]["actions"][0]["physicalDrop"]["autoIncrement"] = {"field": "extra", "alternates": []}
    yield "wrong-generating-field", changed, "A removed generating key must lead with the declared AUTO_INCREMENT field."


def requests(engine, tag, spec):
    release = tag.removeprefix("mysql-")
    refused = 0
    for case in all_cases(spec, tag):
        for route, current in (("analysis", laws.analysis(case, release)), ("direct", laws.direct(case, release))):
            if accepted(case, tag):
                yield f"{'finite' if route == 'analysis' else 'direct'}/{case['id']}", current, item(case, tag), None
            else:
                refused += 1
                identity = f"finite/{case['id']}/{route}"
                yield "negative/" + identity, current, item(case, tag, identity), {"exception": DIAGNOSTIC, "message": reason(case)}
    for label, packet, text in negatives(spec, tag):
        refused += 1
        yield "negative/direct/" + label, packet, None, {"exception": DIAGNOSTIC, "message": text}
    same(negative_count(spec, engine, tag), refused)
    for seed in spec["exploration"]["seeds"]:
        values = list(laws.explorations(all_cases(spec, tag), tag, seed, spec["exploration"]["casesPerSeed"], release_for_expected(tag)))
        for ordinal, case in enumerate(values):
            yield f"seed/{seed}/{ordinal}", laws.analysis(case, release), item(case, tag), None
            yield f"direct-seed/{seed}/{ordinal}", laws.direct(case, release), item(case, tag), None


def check_value(value, observed):
    same("constructed", observed.get("status"))
    same(value["tag"] + PROFILE, observed["profile"])
    same(value["expected"], observed["mysqlAlterForeignKeys"])


def analysis_finite_id(_, response, value, sql):
    same(value["case"]["sql"], sql)
    check_value(value, response)
    return value["case"]["id"]


def direct_finite_id(_, response, value, request):
    same(laws.direct(value["case"], value["tag"].removeprefix("mysql-")), request)
    check_value(value, response)
    return value["case"]["id"]


def sample_forms(forms, response, _, value):
    forms[value["case"]["id"].split("/")[0]] += 1
    actions = response["mysqlAlterForeignKeys"]["actions"]
    forms[f"addition-count/{sum('index' in action for action in actions)}"] += 1
    forms[f"physical-first/{'physicalDrop' in actions[0]}"] += 1
    for change in actions:
        if "physicalDrop" in change:
            forms["role/" + change["physicalDrop"]["role"]] += 1
        if "index" in change:
            for _, descending in change["index"]["fields"]:
                forms[f"descending/{descending}"] += 1


def sample_native_forms(forms, response, _, value):
    forms["native/" + value["case"]["id"].split("/")[0]] += 1


def required_forms(_, tag):
    return {"addition-count/1", "addition-count/2", "addition-count/3", "role/ordinary", "role/primary",
            "role/unique", "physical-first/True", "physical-first/False", "descending/False",
            *laws.AXES, *("native/" + family for family in laws.AXES),
            *({"descending/True"} if tag not in ("mysql-5.6.51", "mysql-5.7.44") else set())}


def observer_request(value, sql):
    case = value["case"]
    return {"sql": sql, "referenceSql": case["sql"], **{k: case[k] for k in ("declarations", "rows", "probe")}}


def observation(payload):
    return payload


def result_status(effect):
    return "ok" if effect[0] == "executed" else [effect[2], effect[1]]


def compare(observed, reference, sql):
    # Legacy failed COPY operations expose temporary table identities assigned
    # by the native server. Everything else in the ordered diagnostics is kept.
    def canonical_result(value):
        result = deepcopy(value)
        for row in result["diagnostics"]:
            if row[0] == "Error" and row[1] == 1025:
                row[2] = re.sub(r"\./ir_fixture/#sql-[0-9A-Fa-f_]+", "./ir_fixture/<temporary-copy>", row[2])
        return result

    require(canonical_result(observed) == canonical_result(reference), "meaning-mismatch",
            {"observed": observed, "reference": reference, "sql": sql})


def check_observation(value, actual, sql):
    require(actual.get("status") == LABEL, "physical-exchange-outside-observer", actual)
    expected = value["case"]["outcomes"][release_for_expected(value["tag"])]
    reference = actual["reference"]
    same(expected["alter"], result_status(reference["effect"]))
    same(expected["probe"], result_status(reference["probe"]))
    compare(actual["observed"], reference, sql)


def rejection_sql(value, _):
    return value["case"]["sql"]


def observe_rejection(_, value, actual, sql):
    check_observation(value, actual, sql)
    return value["identity"]


def fixed_item(tag, coordinate):
    spec, _ = specifications()
    case = next(c for c in all_cases(spec, tag) if c["id"] == coordinate)
    return item(case, tag)


def prepare(_, db):
    value = fixed_item("mysql-9.1.0", "outgoing/explicit/same-name/same-side/False/drop-first")
    good = value["case"]["sql"]
    return [db.request(observer_request(value, sql)) for sql in
            (good + "; SELECT 1", good.replace("fk_test_child", "wrong_table"), good.replace("DROP KEY", "DROP COLUMN"))]


def check_preparation(_, _environment, observed):
    same([{"status": "outside-observer-scope"}] * 3, observed)


def controls():
    data = {"diagnostics": [], "after": {"constraints": [["fk_test_child", "anchor"]]},
            "result": {"tables": {"affected": {"rows": [[33, 3, 3]]}}}}
    compare(data, deepcopy(data), "control")
    changed = {}
    constraint = deepcopy(data)
    constraint["after"]["constraints"][0][1] = "different"
    generated = deepcopy(data)
    generated["result"]["tables"]["affected"]["rows"][0][1] = 4
    for name, witness in (("change-retained-constraint", constraint), ("change-generated-value", generated)):
        try:
            compare(witness, data, "control")
        except Failure as problem:
            same("meaning-mismatch", problem.signal)
            changed[name] = problem.detail
        else:
            raise Failure("physical-exchange-comparator-ignored-change", name)
    return changed


def faults(_, tag):
    owner = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/Exchange/"
    replaced = fixed_item(tag, "outgoing/explicit/same-name/same-side/False/drop-first")
    self_parent = fixed_item(tag, "self-parent/unique/unique/drop-first")
    release = tag.removeprefix("mysql-")
    return [
        RenderFault("omit-resolved-physical-drop", owner + "AlterForeignKeys.php",
                    "$actions[] = 'DROP INDEX ' . $action->name->quoted();", "",
                    replaced, "meaning-mismatch", laws.direct(replaced["case"], release)),
        RenderFault("output-wrong-self-parent-index-column", owner + "IndexAddition.php",
                    "$field->field->quoted()", "'`extra`'",
                    self_parent, "meaning-mismatch", laws.direct(self_parent["case"], release)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
