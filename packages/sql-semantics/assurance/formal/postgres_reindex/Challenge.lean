import ReindexSpec

open ReindexSpec

namespace PostgreSqlReindex

theorem quotedIdentifier (name suffix : Text) (boundary : suffix.head? ≠ some quote) :
  getName (putName name ++ suffix) = some (name, suffix) := by sorry

theorem commandRoundTrip (head : Head) (target : Target) (shape : fits head target) :
  identify (encode head target) = some (head, target) := by sorry

end PostgreSqlReindex
