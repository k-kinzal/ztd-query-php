"""Compare native MySQL signed CASE choices, headings and returned values."""

import json
import sys

import mysql_signed_case_cases as cases
import mysql_signed_membership_cases as signed
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_signed_membership import prepare
from run_server_mysql_signed_range import specifications as profiles
from server_result_cases import match, result_window
from sqlite_domain import RenderFault

LABEL = "mysql-signed-case"
OBSERVER = "mysql_signed_case_observer.php"
CONTRACT_NAME = "mysql-signed-case-evidence-v1.json"
CONTRACT = "db3e34e6cbab5a3225d9cf31e5dcd359486a1f621bbc9647404c1ef55eb4e702"
MEANING = "meaning-mismatch"
LAYOUT = "mysql-signed-case-result-layout"
ILL_FORMED = "mysql-signed-case-empty-admitted"
COMPARATOR_FAULTS = {"Ignore an independently known native nullability mismatch": "nullableHeader"}

def refusal(detail):
    return {"exception":"SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction","message":detail}

SIZE = refusal("A signed CASE requires one to 32 ordered arms.")
FORM = refusal("A signed CASE arm must match the selected decision form.")
SCOPE = refusal("A predicate slot must belong to the SELECT source.")
NON_INTEGRAL = refusal("A membership operand requires a pure signed integer or NULL.")

def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-signed-case-evidence-contract")
    record = json.loads(path.read_text())
    normative = TOOL / record["reference"]["file"]
    require(digest(normative) == record["reference"]["sha256"], "mysql-signed-case-scope-contract")
    scope = json.loads(normative.read_text())
    covered = {*record["faults"], *record["legacyFaults"], "Keep a mutable caller array reference"}
    require(set(scope["faults"]) == covered and scope["finite"]["allowedExclusions"] == scope["exploration"]["allowedExclusions"] == [],
            "mysql-signed-case-coverage-contract")
    specification = {**scope, **record}
    old = ("mysql-5.6.51", "mysql-5.7.44")
    require(specification.get("legacyReleases") == list(old), "mysql-signed-case-legacy-releases")
    return specification, profiles()[1]

def finite_ids(_, spec, _tag):
    in_order = list(cases.grid(spec))
    second = cases.independent_ids()
    require(len(in_order) == len(second) == spec["finite"]["coordinatesPerRoute"] and set(in_order) == second,
            "mysql-signed-case-finite-definition")
    return {coordinate_id(coord) for coord in second}


def coordinate_id(coordinate):
    return json.dumps(coordinate, separators=(",", ":"))


def identify(item, response):
    check_value(item,response)
    return coordinate_id(cases.physical(response["columns"][0][0], response["source"]))

def finite_id(_, response, item):
    return identify(item,response)

def direct_finite_id(_, response, item, request):
    same(cases.direct(item),request)
    return identify(item,response)

def analysis_finite_id(_, response, item, sql):
    same(cases.command(item),sql)
    return identify(item,response)

def case(tag, test=None, arms=(), fallback=None, source="unit"):
    return cases.item(tag,(cases.TERM,test,tuple(arms),fallback),source)

def maximum(tag):
    terminal = ("searched",cases.TWO,cases.literal(97))
    preliminary = ("searched",cases.NULL,cases.literal(-97))
    yield case(tag,arms=(*([preliminary]*31),terminal),fallback=cases.ZERO)
    first = ("simple",cases.TWO,cases.literal(71))
    repeated = ("simple",cases.TWO,cases.literal(-71))
    yield case(tag,cases.TWO,(first,*([repeated]*31)),cases.NULL)

def negatives(tag):
    singular = ("searched",cases.TWO,cases.TWO)
    empty = case(tag)
    yield cases.direct(empty), SIZE
    oversized = case(tag,arms=(singular,)*33)
    yield cases.direct(oversized), SIZE
    yield cases.analyze(oversized), SIZE
    mixed = case(tag, cases.TWO, (singular,))
    yield cases.direct(mixed), FORM
    no_subject = case(tag, arms=(("simple",cases.TWO,cases.TWO),))
    yield cases.direct(no_subject), FORM
    open_edge = case(tag,arms=(singular,),fallback=(cases.TERM,None,(("searched",cases.NULL,cases.FIELD[0]),),cases.ZERO))
    ghost = cases.direct(open_edge)
    yield ghost, SCOPE
    source = cases.analyze(case(tag, arms=(singular,)))
    source["sql"] = "SELECT CASE WHEN 1 THEN '4' ELSE 2 END AS selected"
    yield source, NON_INTEGRAL

def requests(_, tag, spec):
    for number, coordinate in enumerate(cases.grid(spec)):
        trial = cases.item(tag,coordinate)
        yield f"finite/{number}", cases.analyze(trial), trial, None
        yield f"direct/{number}", cases.direct(trial), trial, None
    for number, trial in enumerate(maximum(tag)):
        yield f"maximum/{number}/analyze", cases.analyze(trial), trial, None
        yield f"maximum/{number}/direct", cases.direct(trial), trial, None
    for index, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, reason
    budget = spec["exploration"]
    for seed in budget["seeds"]:
        for index, trial in enumerate(cases.generated(tag, seed, budget["casesPerSeed"])):
            yield f"seed/{seed}/{index}", cases.analyze(trial), trial, None
        for index, trial in enumerate(cases.generated(tag, seed+1, budget["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", cases.direct(trial), trial, None
    # This extra witness makes the release-specific type fault executable on every release.
    trial = case(tag, arms=(("searched",cases.TWO,cases.literal(2147483648)),), fallback=cases.FIELD[2], source="five-widths")
    yield "width-merge", cases.analyze(trial), trial, None

def check_value(trial, response):
    tag = trial["tag"]
    profile = tag + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/"
    profile += "materialized" if tag == "mysql-5.6.51" else "derived-merge=on"
    require(response["status"] == "constructed" and response["profile"] == profile,"mysql-signed-case-positive",response)
    expected = cases.graph(trial)
    heading = expected.pop("signedCaseLayouts")
    require(expected == {field:response.get(field) for field in expected},"mysql-signed-case-graph",{"expected":expected,"observed":response})
    require(heading == response["signedCaseLayouts"],LAYOUT,{"expected":heading,"observed":response["signedCaseLayouts"]})

def check_preparation(_, environment, fixture):
    require(environment["database"][2:] == ["","utf8mb4","utf8mb4"],"mysql-signed-case-profile")
    widths = [[table,column,width.lower(),"YES" if flag else "NO"]
              for table in signed.RECORDS
              for column,width,flag in zip(signed.NAMES,signed.WIDTHS,signed.NULLABILITY[table],strict=True)]
    same(widths,fixture["widths"])

def observer_request(_, sql):
    return {"sql":sql}

def observation(value):
    return value

def check_observation(item, actual, sql):
    expected = cases.native_result(item)
    detail = {"sql":sql,"expected":expected,"observed":actual}
    require(actual["status"] == "rows", "mysql-signed-case-native-refusal", detail)
    require(actual["warnings"] == expected["warnings"], MEANING, detail)
    model = result_window(expected["fields"],expected["rows"],[],None)
    try:
        match(model,actual,sql)
    except Failure as error:
        raise Failure(MEANING,detail) from error

def sample_forms(forms, response, _, item):
    graph = response["columns"][0][0]
    forms[f"source/{item['source']}"] += 1
    forms[f"layout/{response['signedCaseLayouts'][0]['storage']}"] += 1
    def visit(term, level):
        if term is None:
            return
        if term[0] == cases.TERM:
            _, subject, arms, fallback = term
            form = arms[0][0]
            forms[f"shape/{form}"] += 1
            forms[f"depth/{level}"] += 1
            forms[f"arms/{len(arms)}"] += 1
            forms["fallback/omitted" if fallback is None else
                  "fallback/NULL" if fallback[0] == "null" else "fallback/value"] += 1
            visit(subject,level)
            for _,when,then in arms:
                visit(when,level)
                visit(then,level+1)
            visit(fallback,level+1)
        else:
            forms[f"leaf/{term[0]}"] += 1
            if term[0] == "range-integer" and term[2]:
                forms[f"padding/{term[3]}"] += 1
            if term[0] == "range-integer" and term[1] in ("-9223372036854775808","9223372036854775807"):
                forms[f"extreme/{term[1]}"] += 1
    visit(graph,0)

def sample_native_forms(forms,response,_,item):
    metadata = response["fields"][0]
    forms[f"native-nullable/{metadata[2]}"] += 1
    for row in response["rows"]:
        forms["native-NULL" if row[0] is None else "native-value"] += 1

def required_forms(*_):
    return {"source/unit","source/five-widths","source/join","shape/simple","shape/searched","fallback/omitted",
            "fallback/NULL","fallback/value","leaf/integral-slot","leaf/slot","leaf/boolean","leaf/signed-membership",
            "leaf/range-integer","leaf/null","depth/2","depth/3","arms/7","padding/True","padding/False",
            "extreme/-9223372036854775808","extreme/9223372036854775807","native-nullable/True","native-nullable/False",
            "native-NULL","native-value"}

def controls():
    trial = case("mysql-9.1.0",arms=(("searched",cases.TWO,cases.TWO),),fallback=cases.ZERO)
    healthy = {"status":"rows","fields":[[cases.LABEL,"LONGLONG",False,False]],"rows":[[2]],"warnings":0}
    check_observation(trial,healthy,cases.command(trial))
    altered = {**healthy,"fields":[[cases.LABEL,"LONGLONG",True,False]]}
    try:
        check_observation(trial,altered,cases.command(trial))
    except Failure as error:
        same(MEANING,error.signal)
        return {"nullableHeader":error.detail}
    raise Failure("mysql-case-nullability-control-accepted")

def fault_probe(item,response,db):
    if item["term"][2]:
        probe(item,response,db,sys.modules[__name__])
        return
    if rejects(response,SIZE):
        return
    require(response["status"] == "constructed", "mysql-case-empty-control-wrong-failure",response)
    command = response["sql"]
    native = db.request(observer_request(item,command))
    require(native["status"] == "mysql-case-native-error" and native["sqlState"] == "42000",
            "mysql-case-empty-control-wrong-native-failure",native)
    raise Failure(ILL_FORMED,{"sql":command,"observed":native})

def replay_fault(rule,record):
    healthy, mutant = record["healthyResponse"],record["mutantResponse"]
    detail = record["detail"]
    if rule.signal == ILL_FORMED:
        require(rejects(healthy,SIZE) and mutant["status"] == "constructed"
                and detail["observed"]["status"] == "mysql-case-native-error" and detail["observed"]["sqlState"] == "42000",
                ILL_FORMED,record)
        same(mutant["sql"],detail["sql"])
        return
    check_value(rule.item,healthy)
    try:
        check_value(rule.item,mutant)
        same(mutant["sql"],detail["sql"])
        check_observation(rule.item,detail["observed"],mutant["sql"])
    except Failure as error:
        same(rule.signal,error.signal)
        same(detail,error.detail)
        return
    raise Failure("mysql-case-absence-of-fault")

def faults(_,tag):
    directory = "sql-semantics-mysql/src/Ir/"
    renderer = directory + "SelectSql.php"
    empty = case(tag,fallback=cases.ZERO)
    one = ("searched",cases.TWO,cases.literal(41))
    two = ("searched",cases.TWO,cases.literal(81))
    order = case(tag,arms=(one,two),fallback=cases.ZERO)
    fallback = case(tag,arms=(("searched",cases.NULL,cases.literal(41)),),fallback=cases.literal(41))
    bound = case(tag,arms=(("searched",cases.TWO,cases.FIELD[0]),),fallback=cases.FIELD[1],source="five-widths")
    width = case(tag,arms=(("searched",cases.TWO,cases.literal(2147483648)),),fallback=cases.FIELD[2],source="five-widths")
    anchor = "foreach ($choice->arms as $arm) {"
    otherwise = "$sql .= ' ELSE ' . $this->caseOperand($choice->otherwise, $source, $offset, $qualify);"
    render_column = "$this->column($value, $source, $offset, $qualify)"
    return [
        RenderFault("mysql-case-admit-empty",directory+"Expression/Choice/SignedCase.php"," && $arms !== []","",empty,ILL_FORMED,cases.direct(empty)),
        RenderFault("mysql-case-reorder-decisions",renderer,anchor,"foreach (array_reverse($choice->arms) as $arm) {",order,MEANING,cases.direct(order)),
        RenderFault("mysql-case-drop-else",renderer,otherwise,"",fallback,MEANING,cases.direct(fallback)),
        RenderFault("mysql-case-wrong-physical-field",renderer,render_column,
                    "$this->column($value->slot === 0 ? new IntegralColumnReference(1, $value->occurrence) : $value, $source, $offset, $qualify)",
                    bound,MEANING,cases.direct(bound)),
        RenderFault("mysql-case-wrong-width-cohort",directory+"Expression/Choice/CaseLayout.php",
                    "$storage = $this->merge($storage, $next, $legacy);","$storage = $this->merge($storage, $next, !$legacy);",
                    width,LAYOUT,cases.direct(width)),
    ]

if __name__=="__main__":
    main(sys.modules[__name__])
