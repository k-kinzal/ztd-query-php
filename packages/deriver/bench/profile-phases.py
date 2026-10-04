"""Group Xdebug Cachegrind exclusive function costs; accepts plain or gzip files."""
import collections
import gzip
import json
import re
import sys


def phase(name):
    if "Serialization\\" in name:
        return "serialization"
    if any(part in name for part in ("Cache->", "ResultRetention->", "Session->remember")):
        return "retention"
    if any(part in name for part in ("Candidate\\Choices", "Candidates\\ResultBuilder", "Term->native", "Term->isConcrete")):
        return "enumeration-and-result"
    if any(part in name for part in ("Value\\", "Builtin\\", "Derivation->evaluate", "Derivation->intrinsic", "Derivation->element", "ArrayConstruction->append", "DeclarationCoercion")):
        return "partial-evaluation"
    if any(part in name for part in ("Source\\Compilation\\", "Candidate\\Index", "Candidate\\Graph", "ControlFlow\\")):
        return "index-and-lowering"
    if any(part in name for part in ("PhpParser\\", "Source\\", "Project\\", "Session->__construct")):
        return "source-and-declarations"
    if "Evaluation\\Candidate\\" in name or "Candidates\\QueryExecution" in name:
        return "dependency-expansion"
    if "Evaluation\\" in name:
        return "execution-and-shared-semantics"
    return "runtime-autoload-and-other"


for path in sys.argv[1:]:
    names = {}
    costs = collections.Counter()
    current = ""
    called = False
    with (gzip.open(path, "rt") if path.endswith(".gz") else open(path)) as source:
        for line in source:
            match = re.match(r"(c?fn)=\((\d+)\)(?: (.*))?", line)
            if match:
                kind, identity, name = match.groups()
                if name is not None:
                    names[identity] = name
                if kind == "fn":
                    current = names[identity]
            elif line.startswith("calls="):
                called = True
            elif re.match(r"\d+ \d+ -?\d+", line):
                if not called:
                    costs[phase(current)] += int(line.split()[1])
                called = False
    print(json.dumps({"profile": path, "exclusiveMilliseconds": {key: value / 100000 for key, value in sorted(costs.items())}}, indent=2))
