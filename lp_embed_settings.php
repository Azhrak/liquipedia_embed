<?php

// PATHS
define('TMP_DIR', './tmp/liquipedia/');
define('SMILIES_DIR', './forum/images/smilies/');
define('LOGFILE', './log/lp_embed.log');

define('IMG_PROTOSS', 'Protoss.png');
define('IMG_TERRAN', 'Terran.png');
define('IMG_ZERG', 'Zerg.png');
define('IMG_RANDOM', 'Random.png');


// LOG
define('LOG', true);


// CACHE
define('CACHE_DUR', 60 * 30); // 30 min for ongoing events
define('CACHE_DUR_FIN', 60 * 60 * 24 * 90); // 90 days for finished events
define('CACHE_DUR_FUT', 60 * 60 * 24 * 7); // 7 days for future events


// TIME
define('TIMEZONE', 2); // hours to UTC
date_default_timezone_set('Europe/Helsinki');
setlocale(LC_ALL, array('fi_FI.UTF-8', 'fi_FI@euro', 'fi_FI', 'finnish'));


// LAYOUT
define('GROUPS_PER_ROW', 3);


// TEXTS
define('EMPTY_NAME', 'tbd');
define('BYE_NAME', 'bye');

define('BAD_URL', 'Error: Bad URL.');
define('BRACKET_NOT_FOUND', 'No brackets found.');
define('GROUP_NOT_FOUND', 'No groups found.');

define('GROUP_NAME', 'Lohko');
define('SHOW_GROUP_MATCHES', 'Ottelut &darr;');
define('HIDE_GROUP_MATCHES', 'Piilota &uarr;');

define('FINAL_MATCH', 'Finaali');
define('SEMIFINAL_MATCH', 'Semifinaali');
define('BRONZE_MATCH', 'Pronssi');
define('GRAND_FINAL', 'Suurfinaali');
