<?php

enum State { case Ready; public function label() { return "ready"; } }
function subject() { return State::Ready->label(); }
