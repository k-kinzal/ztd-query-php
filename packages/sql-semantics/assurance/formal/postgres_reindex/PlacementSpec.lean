import ReindexSpec

open ReindexSpec

namespace PlacementSpec

def reports : Head → Bool
  | .index _ verbose => verbose
  | .table _ verbose => verbose
  | .schema _ verbose => verbose
  | .database _ verbose => verbose
  | .system verbose => verbose

def ordinaryStem (verbose : Bool) : Text :=
  ['R', 'E', 'I', 'N', 'D', 'E', 'X', ' '] ++ optionPrefix verbose

def placementStem (verbose : Bool) : Text :=
  if verbose then
    ['R', 'E', 'I', 'N', 'D', 'E', 'X', ' ', '(', 'V', 'E', 'R', 'B', 'O', 'S', 'E', ',', ' ',
     'T', 'A', 'B', 'L', 'E', 'S', 'P', 'A', 'C', 'E', ' ']
  else
    ['R', 'E', 'I', 'N', 'D', 'E', 'X', ' ', '(', 'T', 'A', 'B', 'L', 'E', 'S', 'P', 'A', 'C', 'E', ' ']

def stripStem (text : Text) : Option (Bool × Text) :=
  match cut (placementStem true) text with
  | some tail => some (true, tail)
  | none => (cut (placementStem false) text).map (false, ·)

def tailHeader (head : Head) : Text := (header head).drop (ordinaryStem (reports head)).length

def placementSuffix (head : Head) (target : Target) : Text :=
  ')' :: ' ' :: (tailHeader head ++ putTarget target)

def encodePlacement (head : Head) (space : Text) (target : Target) : Text :=
  placementStem (reports head) ++ (putName space ++ placementSuffix head target)

def decodePlacement (text : Text) : Option (Head × Text × Target) := do
  let (verbose, suffix) ← stripStem text
  let (space, tail) ← getName suffix
  match tail with
  | ')' :: ' ' :: rest =>
    let (head, target) ← identify (ordinaryStem verbose ++ rest)
    some (head, space, target)
  | _ => none

end PlacementSpec
