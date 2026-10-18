<?php

trait Label { public function label() { return "ready"; } } enum State { use Label; case Ready; } function subject() { return State::Ready->label(); }
