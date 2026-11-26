import Lean

namespace SignedDecimal

abbrev Digit := Fin 10

def value : List Digit → Nat
  | [] => 0
  | head :: tail => head.val * 10 ^ tail.length + value tail

inductive Canonical : List Digit → Prop
  | zero : Canonical [0]
  | positive (head : Digit) (tail : List Digit) (nonzero : 0 < head.val) :
      Canonical (head :: tail)

def lexBefore : List Digit → List Digit → Prop
  | [], [] => False
  | [], _ :: _ => True
  | _ :: _, [] => False
  | left :: leftTail, right :: rightTail =>
      left.val < right.val ∨ (left.val = right.val ∧ lexBefore leftTail rightTail)

def magnitudeBefore (left right : List Digit) : Prop :=
  left.length < right.length ∨ (left.length = right.length ∧ lexBefore left right)

def before (leftNegative rightNegative : Bool) (left right : List Digit) : Prop :=
  if leftNegative then
    if rightNegative then magnitudeBefore right left else True
  else
    if rightNegative then False else magnitudeBefore left right

def signed (negative : Bool) (digits : List Digit) : Int :=
  if negative then -(Int.ofNat (value digits)) else (Int.ofNat (value digits))

def SignCoherent (negative : Bool) (digits : List Digit) : Prop :=
  negative = true → 0 < value digits

end SignedDecimal
