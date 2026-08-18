<?php
/** Result entry — choose a round. */
$title     = 'Result Entry';
$blurb     = 'Choose a round to record its times and positions.';
$target    = 'results';
$emptyHint = 'Rounds are created under Rounds & Heats — a race needs at least one before results can be recorded.';
require APP_ROOT . '/views/event-user/_round-picker.php';
