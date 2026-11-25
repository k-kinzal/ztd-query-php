"""Compare all six native MySQL truth operations, type metadata and host effects."""

import json
import sys

import mysql_native_truth_cases as law
import mysql_signed_membership_cases as signed
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_signed_membership import OBSERVER, check_preparation, prepare, refusal
from run_server_mysql_signed_range import specifications as profiles
from server_result_cases import match
from sqlite_domain import RenderFault

LABEL = "mysql-native-truth"
CONTRACT_NAME = "mysql-native-truth-evidence-v1.json"
CONTRACT = "04ef1d090ad68a3d9cf44ee4a4ec521bb5b3a9073c31368c2938811fbe49b576"
MEANING = "meaning-mismatch"
SLOT = refusal("A predicate slot must belong to the SELECT source.")
DEPTH = refusal("Predicate depth or expanded node count exceeds the IR profile.")
TYPE = refusal("The operand has no complete MySQL projection representation.")
COMPARATOR_FAULTS = {"Compare only the first observed row":"secondRowMismatch",
                     "Ignore a known native integer or nullability difference":"nullableTruthHeading"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT,"mysql-native-truth-evidence-contract")
    evidence = json.loads(path.read_text())
    reference = TOOL / evidence["reference"]["file"]
    require(digest(reference) == evidence["reference"]["sha256"],"mysql-native-truth-contract")
    scope = json.loads(reference.read_text())
    require(set(scope["faults"]) - set(evidence["faults"]) == {"Lose the typed truth operand scope check"}
            and scope["finite"]["allowedExclusions"] == scope["exploration"]["allowedExclusions"] == [],
            "mysql-native-truth-coverage-contract")
    return {**scope,**evidence},profiles()[1]


def identity(coordinate):
    return json.dumps(coordinate,separators=(",",":"))


def finite_ids(_, spec, _tag):
    ordered=list(law.grid(spec))
    independent=law.independent_ids()
    require(len(ordered)==len(independent)==spec["finite"]["coordinatesPerRoute"]
            and set(ordered)==independent,"mysql-native-truth-finite-definition")
    return {identity(trial) for trial in independent}


def finite_id(_, response, trial):
    check_value(trial,response)
    source=response["source"]
    role="projection" if response["where"] is None else "filter"
    term=response["columns"][0][0] if role=="projection" else response["where"]
    return identity(("unit" if source is None else "five-widths",role,law.physical(term,source)))


def analysis_finite_id(engine,response,item,source):
    same(law.command(item),source)
    return finite_id(engine,response,item)


def direct_finite_id(engine,response,item,request):
    same(law.direct(item),request)
    return finite_id(engine,response,item)


def projection(tag,term,source="unit"):
    return law.case(tag,source,"projection",term)


def nesting(level):
    node=law.BASICS[0]
    for i in range(level):
        node=law.wrap(law.OPERATORS[i % len(law.OPERATORS)],node)
    return node


def negatives(tag):
    yield law.direct(projection(tag,law.wrap("UNKNOWN",law.PHYSICAL[0]))), SLOT
    wrong_kind=law.direct(projection(tag,law.wrap("TRUE",law.BASICS[2]),"five-widths"))
    wrong_kind["source"]=signed.heading(signed.LEFT,[signed.INT])
    wrong_kind["columns"][0][0][2]=["integral-slot",0,0]
    yield wrong_kind,SLOT
    for route in (law.direct,law.analyze):
        yield route(projection(tag,nesting(33))),DEPTH
    source=law.analyze(projection(tag,law.wrap("FALSE",law.BASICS[2])))
    source["sql"]="SELECT ('text' IS FALSE) AS decision"
    yield source,TYPE


def requests(_,tag,spec):
    for n,run in enumerate(law.grid(spec)):
        item=law.case(tag,*run)
        yield f"finite/{n}",law.analyze(item),item,None
        yield f"direct/{n}",law.direct(item),item,None
    maximum = projection(tag,nesting(32))
    yield "maximum/analyze",law.analyze(maximum),maximum,None
    yield "maximum/direct",law.direct(maximum),maximum,None
    for n,(request,reason) in enumerate(negatives(tag)):
        yield f"negative/{n}",request,None,reason
    explore=spec["exploration"]
    for seed in explore["seeds"]:
        for index,item in enumerate(law.generated(tag,seed,explore["casesPerSeed"])):
            yield f"seed/{seed}/{index}",law.analyze(item),item,None
        for index,item in enumerate(law.generated(tag,seed+1,explore["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}",law.direct(item),item,None


def check_value(item,response):
    role=item["placement"]
    profile=item["tag"]+"/utf8mb4/lower-case-table-names=0/sql-mode=empty/native"
    if role not in ("update","delete"):
        profile+="/materialized" if item["tag"]=="mysql-5.6.51" else "/derived-merge=on"
    require(response["status"]=="constructed" and response["profile"]==profile,"mysql-native-truth-positive",response)
    expected=law.graph(item)
    require(expected=={key:response.get(key) for key in expected},"mysql-native-truth-graph",{"expected":expected,"observed":response})


def observer_request(item,command):
    role=item["placement"]
    return {"placement":role,"sql":command,"includeNullability":role=="projection"}


def observation(value):
    return value


def check_observation(item,value,command):
    expected=law.expected(item)
    role=item["placement"]
    detail={"sql":command,"expected":expected,"observed":value}
    require(value["status"]==(role if role in ("update","delete") else "rows"),"mysql-native-truth-native-refusal",detail)
    try:
        require(value["warnings"]==0,MEANING,detail)
        if role in ("update","delete"):
            require(expected["count"]==value["count"] and value["after"]["warnings"]==value["restored"]["warnings"]==0,MEANING,detail)
            match(expected["after"],value["after"],command)
            match(signed.fixture_prediction(signed.LEFT),value["restored"],command)
        else:
            match(expected,value,command)
    except Failure as error:
        raise Failure(MEANING,detail) from error


def sample_forms(forms,response,_,item):
    role=item["placement"]
    forms["placement/"+role]+=1
    scope=response.get("source",response.get("target"))
    sources=[scope["left"],scope["right"]] if role=="join" else [] if scope is None else [scope]
    for source in sources:
        for storage in source.get("columnTypes",["int"]*len(source["columns"])):
            width=storage[1] if isinstance(storage,list) else "INT"
            forms["storage/"+width]+=1
    term=response["columns"][0][0] if role=="projection" else scope["on"] if role=="join" else response["where"]

    def visit(node,depth):
        kind=node[0]
        forms["operand/"+kind]+=1
        if kind==law.TERM:
            forms["operator/"+node[1]]+=1
            forms[f"depth/{depth}"]+=1
            visit(node[2],depth+1)
        elif kind=="signed-membership":
            for child in (node[2],*node[3]):
                visit(child,depth+1)
        elif kind=="null-test":
            visit(node[2],depth+1)
        elif kind=="logic":
            for child in node[2:]:
                visit(child,depth+1)
        elif kind=="range-integer":
            if node[2]:
                forms[f"padding/{node[3]}"]+=1
            if node[1] in ("-9223372036854775808","9223372036854775807"):
                forms["extreme/"+node[1]]+=1
    visit(term,0)


def sample_native_forms(forms,response,_,item):
    role=item["placement"]
    if role in ("update","delete"):
        forms[f"effect/{role}/{response['count']>0}"]+=1
    if role=="projection":
        for result, in response["rows"]:
            forms[f"native/{result}"]+=1


def required_forms(*_):
    return ({f"placement/{role}" for role in ("projection","filter","join","update","delete")}
            | {"operator/"+value for value in law.OPERATORS}
            | {"storage/"+width for width in signed.WIDTHS}
            | {"operand/"+role for role in ("mysql-truth-test","signed-membership","logic","null-test",
                                             "slot","integral-slot","range-integer","null","boolean")}
            | {"depth/3","depth/4","native/0","native/1","padding/True","padding/False",
               "extreme/-9223372036854775808","extreme/9223372036854775807"}
            | {f"effect/{role}/{changed}" for role in ("update","delete") for changed in (True,False)})


def controls():
    item=projection("mysql-9.1.0",law.wrap("TRUE",law.BASICS[2]),"five-widths")
    healthy={"status":"rows","rows":[[1] for _ in signed.RECORDS[signed.LEFT]],"fields":[["decision","LONGLONG",False]],"warnings":0}
    command=law.command(item)
    check_observation(item,healthy,command)
    bad_rows={**healthy,"rows":[[1],[0],*healthy["rows"][2:]]}
    bad_heading={**healthy,"fields":[["decision","LONGLONG",True]]}
    witnesses={}
    for name,mutant in (("secondRowMismatch",bad_rows),("nullableTruthHeading",bad_heading)):
        try:
            check_observation(item,mutant,command)
        except Failure as error:
            same(MEANING,error.signal)
            witnesses[name]=error.detail
        else:
            raise Failure("mysql-native-truth-comparator-accepted",name)
    return witnesses


def replay_fault(rule,record):
    item=rule.item
    detail=record["detail"]
    check_value(item,record["healthyResponse"])
    check_value(item,record["mutantResponse"])
    same(record["mutantResponse"]["sql"],detail["sql"])
    try:
        check_observation(item,detail["observed"],detail["sql"])
    except Failure as error:
        same(rule.signal,error.signal)
        same(detail,error.detail)
        return
    raise Failure("mysql-native-truth-fault-not-reproduced")


def faults(_,tag):
    file="sql-semantics-mysql/src/Ir/SelectSql.php"
    truth=projection(tag,law.wrap("TRUE",law.BASICS[2]))
    nested=projection(tag,law.wrap("TRUE",law.wrap("FALSE",law.BASICS[1])))
    op=("logic","AND",law.wrap("TRUE",law.PHYSICAL[0]),law.wrap("FALSE",tuple(signed.reference(0,1))))
    bound=law.case(tag,"five-widths","join",op)
    value="$operand = $test->operand;"
    return [
        RenderFault("mysql-truth-render-different-operation",file,"$sql . ' IS ' . $test->operator->value","$sql . ' IS FALSE'",
                    truth,MEANING,law.direct(truth)),
        RenderFault("mysql-truth-erase-nested-operand",file,value,
                    "$operand = $test->operand instanceof TruthTest ? new BooleanLiteral(false) : $test->operand;",
                    nested,MEANING,law.direct(nested)),
        RenderFault("mysql-truth-change-physical-occurrence",file,value,value+"\n        "
                    "if ($operand instanceof IntegralColumnReference && $operand->occurrence === 1) {"
                    " $operand = new IntegralColumnReference($operand->slot); }",
                    bound,MEANING,law.direct(bound)),
    ]


if __name__=="__main__":
    main(sys.modules[__name__])
