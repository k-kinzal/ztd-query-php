import ReindexSpec

open ReindexSpec

namespace PostgreSqlReindex

axiom quotedIdentifier (name suffix : Text) (boundary : suffix.head? ≠ some quote) :
  getName (putName name ++ suffix) = some (name, suffix)

axiom commandRoundTrip (head : Head) (target : Target) (shape : fits head target) :
  identify (encode head target) = some (head, target)

end PostgreSqlReindex
