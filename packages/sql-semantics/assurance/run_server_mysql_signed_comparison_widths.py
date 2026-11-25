"""Verify signed MySQL comparisons and BETWEEN across all five native input widths."""

import json
import sys

import mysql_signed_comparison_widths_cases as laws
import mysql_signed_membership_cases as signed
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_native_truth import check_native_observation, observer_request, observation
from run_server_mysql_signed_membership import OBSERVER, check_preparation, prepare, refusal
from run_server_mysql_signed_range import specifications as profiles
from sqlite_domain import RenderFault

LABEL="mysql-signed-comparison-widths"
CONTRACT_NAME="mysql-signed-comparison-widths-evidence-v2.json"
CONTRACT="c20091712f5939bbd445b4eca72a7070e03dcc758489c8657c5b6896803b4ba7"
MEANING="meaning-mismatch"
REFERENCE=refusal("A predicate slot must belong to the SELECT source.")
RANGE=refusal("A range slot must belong to the SELECT source.")
COMPARATOR_FAULTS={
    "Ignore a native NULL distinct from zero":"nullIsNotZero",
    "Treat legacy IS NOT UNKNOWN as the complement of IS UNKNOWN":"legacyNullInspection",
}


def specifications():
    path=TOOL/CONTRACT_NAME
    require(digest(path)==CONTRACT,"mysql-signed-comparison-schedule")
    evidence=json.loads(path.read_text())
    file=TOOL/evidence["reference"]["file"]
    require(digest(file)==evidence["reference"]["sha256"],"mysql-signed-comparison-contract")
    scope=json.loads(file.read_text())
    base=scope["extends"]
    require(digest(TOOL/base["path"])==base["sha256"],"mysql-signed-comparison-prior-contract")
    require(set(scope["faults"])-set(evidence["faults"])=={"Erase an operand scope check"}
            and scope["finite"]["allowedExclusions"]==scope["exploration"]["allowedExclusions"]==[],
            "mysql-signed-comparison-coverage-contract")
    return {**scope,**evidence},profiles()[1]


def coordinate(item):
    return json.dumps(item,separators=(",",":"))


def finite_ids(_,spec,_tag):
    ordered=list(laws.grid(spec))
    independent=laws.independent_ids()
    require(len(list(laws.composed_grid(spec)))==spec["finite"]["nullSafeComposition"]["coordinatesPerRoute"],
            "mysql-signed-comparison-composition-finite-definition")
    require(set(ordered)==independent and len(ordered)==len(independent)==spec["finite"]["coordinatesPerRoute"],
            "mysql-signed-comparison-finite-definition")
    return {coordinate(key) for key in independent}


def finite_id(_,response,item):
    check_value(item,response)
    if "assignments" in response:
        source,role,term=response["target"],"update",response["where"]
    elif "target" in response:
        source,role,term=response["target"],"delete",response["where"]
    else:
        source=response["source"]
        if source is not None and "on" in source:
            role,term="join",source["on"]
        elif response["where"] is not None:
            role,term="filter",response["where"]
        else:
            role,term="projection",response["columns"][0][0]
    return coordinate(("unit" if source is None else "five-widths",role,laws.physical(term,source)))


def analysis_finite_id(engine,response,item,command):
    same(laws.command(item),command)
    return finite_id(engine,response,item)


def direct_finite_id(engine,response,item,request):
    same(laws.direct(item),request)
    return finite_id(engine,response,item)


def projection(tag,term,source="unit"):
    return laws.trial(tag,source,"projection",term)


def negatives(tag):
    val=tuple(signed.literal(2))
    field=laws.WIDTHS[0]
    yield laws.direct(projection(tag,laws.compare("<",field,val))),REFERENCE
    yield laws.direct(projection(tag,laws.compare("<",val,field))),REFERENCE
    yield laws.direct(projection(tag,laws.interval(False,val,field,val))),RANGE
    signed_slot=laws.direct(projection(tag,laws.compare("<",val,val),"five-widths"))
    signed_slot["source"]=signed.heading(signed.LEFT,[0])
    signed_slot["columns"][0][0][2]=["slot",0]
    yield signed_slot,REFERENCE
    statement=laws.analyze(projection(tag,laws.compare("<",val,val)))
    yield {**statement,"sql":"SELECT (9223372036854775808 = 2) AS decision"},refusal(
        "Range integer is outside the signed 64-bit range.")
    yield {**statement,"sql":"SELECT (n <=> 2) AS decision FROM unsigned_probe",
           "declarations":["CREATE TABLE ir_fixture.unsigned_probe (n BIGINT UNSIGNED)"]},refusal(
        "A membership column requires signed integral storage.")


def requests(_,tag,spec):
    for index,xyz in enumerate(laws.grid(spec)):
        trial=laws.trial(tag,*xyz)
        yield f"finite/{index}",laws.analyze(trial),trial,None
        yield f"direct/{index}",laws.direct(trial),trial,None
    for index,(request,reason) in enumerate(negatives(tag)):
        yield f"negative/{index}",request,None,reason
    budget=spec["exploration"]
    for seed in budget["seeds"]:
        for index,trial in enumerate(laws.generated(tag,seed,budget["casesPerSeed"])):
            yield f"seed/{seed}/{index}",laws.analyze(trial),trial,None
        for index,trial in enumerate(laws.generated(tag,seed+1,budget["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}",laws.direct(trial),trial,None


def check_value(item,response):
    profile=item["tag"]+"/utf8mb4/lower-case-table-names=0/sql-mode=empty/native"
    if item["placement"] not in ("update","delete"):
        profile+="/materialized" if item["tag"]=="mysql-5.6.51" else "/derived-merge=on"
    require(response["status"]=="constructed" and response["profile"]==profile,"mysql-signed-comparison-positive",response)
    expected=laws.graph(item)
    require(expected=={key:response.get(key) for key in expected},"mysql-signed-comparison-graph",{"expected":expected,"observed":response})


def check_observation(item,value,command):
    check_native_observation(item,value,command,laws.expected(item),"mysql-signed-comparison")


def sample_forms(forms,response,_,item):
    role=item["placement"]
    forms["placement/"+role]+=1
    source=response.get("source",response.get("target"))
    sources=[source["left"],source["right"]] if role=="join" else [] if source is None else [source]
    for heading in sources:
        for storage in heading.get("columnTypes",["int"]*len(heading["columns"])):
            forms["width/"+(storage[1] if isinstance(storage,list) else "INT")]+=1

    def visit(term,depth):
        kind=term[0]
        forms["operand/"+kind]+=1
        if kind==laws.CMP:
            forms["operator/"+term[1]]+=1
        elif kind==laws.BETWEEN:
            forms[f"range/{term[1]}"]+=1
        if kind in (laws.CMP,laws.BETWEEN,laws.TRUTH,"logic","null-test"):
            forms[f"depth/{depth}"]+=1
            for child in term[2:]:
                visit(child,depth+1)
        elif kind in ("integer","range-integer") and term[1] in ("-9223372036854775808","9223372036854775807"):
            forms["edge/"+term[1]]+=1
    root=response["columns"][0][0] if role=="projection" else source["on"] if role=="join" else response["where"]
    visit(root,0)


def sample_native_forms(forms,observed,_,item):
    role=item["placement"]
    if role in ("update","delete"):
        forms[f"effect/{role}/{observed['count']!=0}"]+=1
    if role=="projection":
        forms[f"heading/{observed['fields'][0][2]}"]+=1
        for value, in observed["rows"]:
            forms[f"result/{value}"]+=1


def required_forms(*_):
    return ({f"placement/{role}" for role in ("projection","filter","join","update","delete")}
            | {"operator/"+op for op in laws.SYMBOLS}
            | {"width/"+name for name in signed.WIDTHS}
            | {"range/True","range/False","result/0","result/1","result/None","depth/1","operand/null",
               "operand/logic","operand/mysql-truth-test","operand/integral-slot","operand/slot",
               "heading/True","heading/False","edge/-9223372036854775808","edge/9223372036854775807"}
            | {f"effect/{role}/{change}" for role in ("update","delete") for change in (True,False)})


def controls():
    positive=laws.compare("=",laws.NIL,tuple(signed.literal(2)))
    legacy=(laws.TRUTH,"NOT UNKNOWN",laws.compare("<=>",laws.NIL,laws.NIL))
    receipts={}
    for name,trial,result,mutant,nullable in (
        ("nullIsNotZero",projection("mysql-9.1.0",positive),None,0,True),
        ("legacyNullInspection",projection("mysql-5.6.51",legacy),0,1,False),
    ):
        healthy={"status":"rows","fields":[["decision","LONGLONG",nullable]],"rows":[[result]],"warnings":0}
        check_observation(trial,healthy,laws.command(trial))
        try:
            check_observation(trial,{**healthy,"rows":[[mutant]]},laws.command(trial))
        except Failure as error:
            same(MEANING,error.signal)
            receipts[name]=error.detail
        else:
            raise Failure("mysql-signed-comparison-comparator-ignored-difference",name)
    return receipts


def replay_fault(rule,record):
    item,detail=rule.item,record["detail"]
    check_value(item,record["healthyResponse"])
    check_value(item,record["mutantResponse"])
    same(record["mutantResponse"]["sql"],detail["sql"])
    try:
        check_observation(item,detail["observed"],detail["sql"])
    except Failure as error:
        same(rule.signal,error.signal)
        same(detail,error.detail)
        return
    raise Failure("mysql-signed-comparison-fault-not-reproduced")


def faults(_,tag):
    emitter="sql-semantics-mysql/src/Ir/SelectSql.php"
    val=tuple(signed.literal(2))
    scalar=projection(tag,laws.compare("<",laws.WIDTHS[0],laws.WIDTHS[1]),"five-widths")
    nulls=projection(tag,laws.compare("<=>",laws.NIL,laws.NIL))
    span=projection(tag,laws.interval(False,val,tuple(signed.literal(1)),tuple(signed.literal(4))))
    inverted=projection(tag,laws.interval(True,val,tuple(signed.literal(1)),tuple(signed.literal(4))))
    return [
        RenderFault("mysql-comparison-read-other-native-slot",emitter,
                    "$left = $this->rangeOperand($test->left, $source, $offset, $qualify);",
                    "$left = $this->rangeOperand($test->right, $source, $offset, $qualify);",scalar,MEANING,laws.direct(scalar)),
        RenderFault("mysql-comparison-lose-null-safe-equality",emitter,
                    "$left . ' ' . $test->operator->value . ' ' . $right",
                    "$left . ' = ' . $right",nulls,MEANING,laws.direct(nulls)),
        RenderFault("mysql-range-swap-bound-positions",emitter,
                    "$lower . ' AND ' . $upper","$upper . ' AND ' . $lower",span,MEANING,laws.direct(span)),
        RenderFault("mysql-range-drop-native-negation",emitter,
                    "($range->negated ? ' NOT BETWEEN ' : ' BETWEEN ')","' BETWEEN '",inverted,MEANING,laws.direct(inverted)),
    ]


if __name__=="__main__":
    main(sys.modules[__name__])
