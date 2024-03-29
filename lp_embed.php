<?php
include 'lp_embed_settings.php';
define('CACHE_FIN_SUFFIX', '_fin'); //cache file suffix for finished events

// Load parameters
$url = (isset($_GET['url'])) ? trim($_GET['url']) : '';
$mode = (isset($_GET['mode'])) ? strtolower(trim($_GET['mode'])) : 'bracket'; // bracket or group
$showmatches = (isset($_GET['showmatches'])); // is matches list dropped down as default
$finished = (isset($_GET['finished'])); // force finished, so that we always use cache
$start_time = (isset($_GET['start_time'])) ? strtotime($_GET['start_time']) : ''; // use longer cache before start time
$cache_dur_fixed = (isset($_GET['cache_dur'])) ? trim($_GET['cache_dur']) : ''; // cache duration in string
$use_cache = (!isset($_GET['nocache']));
$bracket_number = (isset($_GET['bnum']) && is_numeric($_GET['bnum'])) ? (int) abs($_GET['bnum']) : 1; //in case there are multiple brackets in the same page, on default use the first one
$ro_start = (isset($_GET['ro']) && is_numeric($_GET['ro'])) ? $ro_start = (int) abs($_GET['ro']) : null;
$ro_end = (isset($_GET['maxro']) && is_numeric($_GET['maxro'])) ? $ro_end = (int) abs($_GET['maxro']) : null;
$group_stage = (isset($_GET['gstage']) && is_numeric($_GET['gstage'])) ? (int) abs($_GET['gstage']) : 1; //in case there are multiple group stages in the same page, on default use the first one
$group_select = (isset($_GET['gselect']) && $_GET['gselect']) ? $_GET['gselect'] : null; //a selection or range of groups to display
$country_filter = (isset($_GET['country'])) ? trim(strtolower($_GET['country'])) : null; // filter to show only groups with given country in them
$player_filter = (isset($_GET['player'])) ? trim(strtolower($_GET['player'])) : null; // filter to show only groups with given player in them
$use_localtime = (isset($_GET['localtime']) && $_GET['localtime'] == 1);
$debug = isset($_GET['debug']);


$title = $page = $game = '';
if (preg_match('/(?:https?:\/\/)?(?:wiki\.teamliquid|liquipedia)\.net\/starcraft(2?)\/([^\?]+)/', $url, $matches)) {
  $game = ($matches[1] == '2') ? 'sc2' : 'sc'; //sc1 or sc2 wiki
  $page = $matches[2];
  $title = preg_replace('/[^\w\d_\.-]/', '_', $page);
} else {
  echo BAD_URL;
  return;
}

// Fix in case url parameter is badly formed
if (strpos($url, '?') !== false) {
  $query = substr($url, strpos($url, '?'));
  if (preg_match('/ro=([\d]+)/', $query, $matches)) $ro_start = $matches[1];
}

if (!empty($group_select)) {
  // extract group selections parameter; accept comma delimited values as well as ranges separated by minus sign
  $group_select = explode(',', $group_select);
  $selections = array();
  foreach ($group_select as $v) {
    if (strpos($v, '-') !== false) {
      $range = explode('-', $v);
      if (empty($range[0])) $range[0] = 1;
      if (empty($range[1])) $range[1] = 99; //print to the end
      foreach (range($range[0], $range[1]) as $r) {
        $selections[] = (int) $r;
      }
    } else {
      $selections[] = (int) $v;
    }
  }
  $group_select = $selections;
}

$prefix = ''; //cache file prefix
$prefix = ($game == 'sc') ? 'sc-' : ''; //add prefix for sc1 wiki files
$cachefile = TMP_DIR . $prefix . $title;
$suffix = ''; //cache file suffix
$cache_dur = CACHE_DUR;
if (time() < $start_time) {
  $cache_dur = CACHE_DUR_FUT;
}
if (!file_exists($cachefile) && file_exists($cachefile . CACHE_FIN_SUFFIX)) {
  $suffix = CACHE_FIN_SUFFIX;
  $cache_dur = CACHE_DUR_FIN;
}

if (!empty($cache_dur_fixed)) {
  $cache_dur = strtotime($cache_dur_fixed, 0);
}

$embeds = $brackets = $groups = array();

if (!$use_cache || !file_exists($cachefile . $suffix) || (time() - filemtime($cachefile . $suffix) > $cache_dur)) {
  $wiki = ($game == 'sc') ? 'starcraft' : 'starcraft2';
  //$content_url = 'http://wiki.teamliquid.net/'.$wiki.'/index.php?action=render&title='.$page; //load only the content - smaller but not in cache
  $content_url = 'https://liquipedia.net/' . $wiki . '/' . $page;

  $html = get_with_curl($content_url);

  $brackets = parse_brackets($html);

  $groups = parse_groups($html);

  //if both empty, use cached data if it exists
  if (empty($groups) && empty($brackets) && file_exists($cachefile . $suffix)) {
    $embeds = unserialize(file_get_contents($cachefile . $suffix));
  } else {
    $bracket_finished = $groups_finished = true;
    $bracket_finished = (empty($brackets) || (!empty($brackets) && $brackets[count($brackets) - 1]['finished']));

    if (!empty($groups)) {
      $last_group = end($groups[count($groups) - 1]);
      $groups_finished = $last_group['finished'];
    }

    if (($bracket_finished && $groups_finished) || $finished) {
      if (empty($suffix)) {
        if (file_exists($cachefile . $suffix)) unlink($cachefile . $suffix);
        $suffix = CACHE_FIN_SUFFIX;
      }
    } elseif (!empty($suffix)) { // not finished but somehow marked finished before
      if (file_exists($cachefile . $suffix)) unlink($cachefile . $suffix);
      $suffix = '';
    }

    $embeds = array(
      'brackets' => $brackets,
      'groups' => $groups
    );

    file_put_contents($cachefile . $suffix, serialize($embeds));
  }
  mylog(array('title' => $title, 'cache_dur' => $cache_dur, 'group_count' => count($groups), 'bracket_count' => count($brackets), 'get_vars' => $_GET));
} else {
  $embeds = unserialize(file_get_contents($cachefile . $suffix));
}

if ($debug) {
  debug($embeds);
}


// PRINT BRACKET
if ($mode == 'bracket') {
  if (isset($embeds['brackets'])) $brackets = $embeds['brackets'];
  if (empty($brackets)) die(BRACKET_NOT_FOUND);
  $bracket = (isset($brackets[$bracket_number - 1])) ? $brackets[$bracket_number - 1] : $brackets[0];
  // debug($bracket);
?>
  <!DOCTYPE HTML>
  <html>

  <head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="lp_embed.css<?php echo '?' . @filemtime('lp_embed.css') ?>" type="text/css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script type="text/javascript">
      //Drag scroll ...
      var clicked = false,
        clickY, clickX;
      $(document).on({
        'mousemove': function(e) {
          clicked && updateScrollPos(e);
        },
        'mousedown': function(e) {
          clicked = true;
          clickY = e.pageY;
          clickX = e.pageX;
        },
        'mouseup': function() {
          clicked = false;
          $('html').css('cursor', 'auto');
        }
      });

      var updateScrollPos = function(e) {
        $('html').css('cursor', 'move');
        $(window).scrollTop($(window).scrollTop() + (clickY - e.pageY));
        $(window).scrollLeft($(window).scrollLeft() + (clickX - e.pageX));
      }
      // ... Drag scroll
    </script>
  </head>

  <body>
    <?php $round_count = 0; ?>
    <?php $has_grand_final = isset($bracket['rounds']['grand_final']) && !empty($bracket['rounds']['grand_final']); ?>
    <?php if (!empty($bracket)) : ?>
      <div class="forum_bracket <?php echo 'forum_bracket_size_' . count($bracket['rounds']); ?> <?php echo 'forum_bracket_ro_' . array_keys($bracket['rounds'])[0]; ?>">
        <?php foreach ($bracket['rounds'] as $round => $matches) : ?>
          <?php if (!empty($ro_start) && is_numeric($round) && $ro_start < $round) continue; ?>
          <?php if (!empty($ro_end) && is_numeric($round) && $round < $ro_end) continue; ?>

          <?php $last_round = (($round == 2 && !$has_grand_final) || $round == 'grand_final') ? 'forum_bracket_round_last' : '';
          $round_count++;
          $class = 'forum_bracket_round forum_bracket_round_' . $round_count;
          if ($round == 'bronze') {
            $class = 'forum_bracket_bronze_' . $round_count;
          } ?>

          <div class="<?php echo $class . ' ' . $last_round ?>">
            <div class="forum_bracket_round_name">
              <?php echo round_name($round, $bracket['lb_rounds']) ?>
            </div>

            <?php print_bracket_matches($matches, $round, false, 1, $has_grand_final); ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="clear:both"></div>

      <?php if ($bracket['lb_rounds']) : ?>
        <?php $round_count = 0; ?>
        <div class="forum_bracket lower_forum_bracket <?php echo 'forum_bracket_size_' . (count($bracket['lb_rounds']) * 2); ?> forum_bracket<?php echo array_keys($bracket['rounds'])[0] ?>_lower">
          <?php foreach ($bracket['lb_rounds'] as $round => $stages) : ?>
            <?php $round_count++; ?>
            <?php foreach ($stages as $stage => $matches) : ?>
              <?php if (!empty($ro_start) && is_numeric($round) && $ro_start < $round) continue; ?>
              <?php if (!empty($ro_end) && is_numeric($round) && $round < $ro_end) continue; ?>

              <div class="forum_bracket_round forum_bracket_round_<?php echo $round_count; ?> forum_bracket_stage_<?php echo $stage ?>">
                <div class="forum_bracket_round_name">
                  <?php echo 'RO' . $round . ', S' . $stage ?>
                </div>

                <?php print_bracket_matches($matches, $round, true, $stage); ?>
              </div>

            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
        <div style="clear:both"></div>
      <?php endif; ?>

    <?php endif; ?>
  </body>

  </html>


<?php }
// PRINT GROUPS
elseif ($mode == 'group') {
  if (isset($embeds['groups'])) $groups = $embeds['groups'];
  // debug($groups);
  if (empty($groups)) die(GROUP_NOT_FOUND);
  $groups = (isset($groups[$group_stage - 1])) ? $groups[$group_stage - 1] : $groups[0];
?>
  <!DOCTYPE HTML>
  <html>

  <head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="lp_embed.css<?php echo '?' . @filemtime('lp_embed.css') ?>" type="text/css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script type="text/javascript">
      function toggle(button, id) {
        var e = document.getElementById(id);
        if (e.style.display == 'block') {
          e.style.display = 'none';
          button.innerHTML = '<?php echo SHOW_GROUP_MATCHES ?>';
        } else {
          e.style.display = 'block';
          button.innerHTML = '<?php echo HIDE_GROUP_MATCHES ?>';
        }
      }
    </script>
  </head>

  <body>
    <?php if (!empty($groups)) : ?>
      <div class="groups">
        <?php $counter = 0; ?>

        <div class="group_row">
          <?php foreach ($groups as $id => $group) : ?>

            <?php if (isset($group['countries']) && !empty($country_filter) && !in_array($country_filter, $group['countries'])) continue; ?>
            <?php if (isset($group['player_names']) && !empty($player_filter) && !in_array($player_filter, $group['player_names'])) continue; ?>
            <?php if (!empty($group_select) && !in_array(($id + 1), $group_select)) continue; ?>

            <?php if ($counter++ % GROUPS_PER_ROW == 0) : ?>
        </div>
        <div class="group_row">
        <?php endif; ?>

        <div class="group <?php echo ($counter % GROUPS_PER_ROW == 0) ? 'right' : ''; ?>">
          <table class="group_players">
            <tr>
              <th colspan="4" class="group_name">
                <?php echo GROUP_NAME . ' ' . $group['name'] ?>
                <?php $time = ($use_localtime) ? $group['time_local'] : $group['time']; ?>
                <?php if (!empty($time)) : ?>
                  <?php $time = strftime("%a, %d. %b, %H:%M", $time); ?>
                  <span class="group_time"><?php echo preg_replace('/[^öä\w\d ,:\.]/', '', $time); //date("j.n, H:i", $group['time']);
                                            ?></span>
                <?php endif; ?>
              </th>
            </tr>
            <?php if (!empty($group['players'])) : ?>
              <?php foreach ($group['players'] as $j => $player) : ?>
                <tr class="group_player <?php echo ($player['advance']) ? 'player_advance' : '' ?>">

                  <td class="player_position">
                    <?php echo (0 < $player['position']) ? $player['position'] : '&bull;' ?>
                  </td>

                  <td class="player_name <?php echo ($j == 0) ? 'first_player_row' : ''; ?>">
                    <?php if (!empty($player['name'])) : ?>
                      <?php if (!empty($player['country_short']) && !empty($player['country'])) : ?>
                        <img src="<?php echo SMILIES_DIR . $player['country_short'] ?>.gif" title="<?php echo $player['country'] ?>" alt="">
                      <?php endif; ?>
                      <?php if (!empty($player['race'])) : ?>
                        <img src="<?php echo race_icon($player['race']) ?>" title="<?php echo $player['race'] ?>" alt="">
                      <?php endif; ?>
                      <?php echo ($player['advance']) ? '<strong>' . $player['name'] . '</strong>' : $player['name'] ?>
                    <?php else : ?>
                      <?php echo EMPTY_NAME; ?>
                    <?php endif; ?>
                  </td>

                  <td class="player_match_score">
                    <?php if (!empty($player['score']['match_score'])) : ?>
                      <strong><?php echo $player['score']['match_score']['win'] . '-' . $player['score']['match_score']['loss']; ?></strong>
                    <?php endif; ?>
                  </td>

                  <td class="player_map_score">
                    <?php if (!empty($player['score']['map_score'])) : ?>
                      <?php echo $player['score']['map_score']['win'] . '-' . $player['score']['map_score']['loss']; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </table>

          <?php if (!empty($group['matches'])) : ?>
            <table class="group_matches">
              <thead>
                <tr>
                  <th colspan="4"><a class="show_button" href="javascript:toggle(this, 'matches_<?php echo $id ?>')"><?php echo SHOW_GROUP_MATCHES ?></a></th>
                </tr>
              </thead>
              <tbody id="matches_<?php echo $id ?>" <?php if (!$showmatches) echo 'style="display:none"'; ?>>
                <?php foreach ($group['matches'] as $i => $match) : ?>
                  <tr>
                    <td class="matches_name left">
                      <?php if (isset($group['players'][$match['player1']])) : ?>
                        <?php $player = $group['players'][$match['player1']]; ?>
                        <?php echo (0 < strlen($match['winner']) && $match['winner'] == 0) ? '<strong>' . $player['name'] . '</strong>' : $player['name'] ?>
                      <?php else : ?>
                        <?php echo EMPTY_NAME ?>
                      <?php endif; ?>
                    </td>

                    <td class="matches_score left"><?php echo $match['score1'] ?></td>
                    <td class="matches_score right"><?php echo $match['score2'] ?></td>

                    <td class="matches_name right">
                      <?php if (isset($group['players'][$match['player2']])) : ?>
                        <?php $player = $group['players'][$match['player2']]; ?>
                        <?php echo (0 < strlen($match['winner']) && $match['winner'] == 1) ? '<strong>' . $player['name'] . '</strong>' : $player['name'] ?>
                      <?php else : ?>
                        <?php echo EMPTY_NAME ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <?php if (!empty($group['crosstable']) && !empty($group['crosstable']['table'])) : ?>
            <table class="group_crosstable">
              <thead>
                <tr>
                  <th colspan="<?php echo count($group['crosstable']['table']) ?>">
                    <a class="show_button" href="javascript:toggle(this, 'crosstable_<?php echo $id ?>')"><?php echo SHOW_GROUP_MATCHES ?></a>
                  </th>
                </tr>
              </thead>
              <tbody id="crosstable_<?php echo $id ?>" <?php if (!$showmatches) echo 'style="display:none"'; ?>>
                <?php for ($row = 0; $row < count($group['crosstable']['table']); $row++) : ?>
                  <tr>
                    <?php for ($col = 0; $col < count($group['crosstable']['table']); $col++) : ?>
                      <?php if ($col == 0 || $row == count($group['crosstable']['table']) - 1) : ?>
                        <th class="crosstable_name">
                          <?php $player = $group['crosstable']['table'][$row][$col]; ?>
                          <?php if (!empty($player) && !empty($player['name'])) : ?>
                            <?php echo $player['name'] ?>
                          <?php endif; ?>
                        </th>
                      <?php else : ?>
                        <td class="crosstable_score">
                          <?php $match = $group['crosstable']['table'][$row][$col]; ?>
                          <?php if (!empty($match) && isset($match['score1'])) : ?>
                            <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    <?php endfor; ?>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <div style="clear:both"></div>
  </body>

  </html>


<?php } ?>
<?php
function print_bracket_matches($matches, $round, $lower_bracket = false, $stage = 1, $has_grand_final = false)
{
  foreach ($matches as $i => $match) : ?>
    <div class="forum_bracket_match forum_bracket_<?php echo ($i % 2 == 0) ? 'even' : 'odd' ?>">

      <div class="forum_bracket_contestant">
        <span class="forum_bracket_score"><?php echo $match['player1']['score'] ?></span>

        <span class="forum_bracket_name">
          <?php if (!empty($match['player1']['country_short'])) : ?>
            <img src="<?php echo SMILIES_DIR . $match['player1']['country_short'] ?>.gif" title="<?php echo $match['player1']['country'] ?>">
          <?php endif; ?>
          <?php if (!empty($match['player1']['race'])) : ?>
            <img src="<?php echo race_icon($match['player1']['race']) ?>" title="<?php echo $match['player1']['race'] ?>">
          <?php endif; ?>
          <?php echo ($match['player1']['winner']) ? '<strong>' . $match['player1']['name'] . '</strong>' : $match['player1']['name'] ?>
        </span>
      </div>

      <div class="forum_bracket_contestant">
        <span class="forum_bracket_score"><?php echo $match['player2']['score'] ?></span>

        <span class="forum_bracket_name">
          <?php if (!empty($match['player2']['country_short'])) : ?>
            <img src="<?php echo SMILIES_DIR . $match['player2']['country_short'] ?>.gif" title="<?php echo $match['player2']['country'] ?>">
          <?php endif; ?>
          <?php if (!empty($match['player2']['race'])) : ?>
            <img src="<?php echo race_icon($match['player2']['race']) ?>" title="<?php echo $match['player2']['race'] ?>">
          <?php endif; ?>
          <?php echo ($match['player2']['winner']) ? '<strong>' . $match['player2']['name'] . '</strong>' : $match['player2']['name'] ?>
        </span>
      </div>

    </div>
    <?php if ($round == 'bronze') : ?>
      </div>
    <?php elseif ($round == 12) : ?>
      <div class="forum_bracket_line_join"></div>
      <div class="forum_bracket_line_vertical"></div>
    <?php elseif ($lower_bracket && $stage == 1) : ?>
      <div class="forum_bracket_line_join from_upper_bracket"></div>
      <div class="forum_bracket_line_join"></div>
      <div class="forum_bracket_line_vertical"></div>
    <?php elseif ($lower_bracket && $stage == 2 && $round == 2) : ?>
      <div class="forum_bracket_line_join to_grand_final"></div>
    <?php elseif ($has_grand_final && $round == 2) : ?>
      <div class="forum_bracket_line_join to_grand_final"></div>
      <div class="forum_bracket_line_vertical to_grand_final"></div>
      <div class="forum_bracket_line_join to_grand_final_bottom"></div>
    <?php elseif ($i < count($matches) - 1 && $i % 2 == 0) : ?>
      <div class="forum_bracket_line_join"></div>
      <div class="forum_bracket_line_vertical"></div>
    <?php endif; ?>
<?php endforeach;
}

function parse_brackets($html)
{
  global $debug;

  if (!preg_match_all('/class="(?:\w+-)bracket(?:"| [^"]+")/', $html, $matches, PREG_OFFSET_CAPTURE, 10000)) {
    if (!preg_match('/id="Playoffs"|id="Brackets?"|bgcolor="#f2f2f2">Finals/', $html, $matches, PREG_OFFSET_CAPTURE, 10000)) {
      // echo "Error: No bracket found.";
      return array();
    }
  }

  $offsets = array();
  if (is_array($matches[0][0]) && isset($matches[0][0][1])) {
    foreach ($matches[0] as $match) {
      $offsets[] = $match[1];
    }
  } else {
    $offsets[] = $matches[0][1];
  }

  for ($k = 0; $k < count($offsets); $k++) {

    $offset_start = $offsets[$k];
    if (isset($offsets[$k + 1])) {
      $html_slice = substr($html, $offset_start, $offsets[$k + 1] - $offset_start);
    } else {
      $html_slice = substr($html, $offset_start);
    }

    $bracket = array();

    if (preg_match('/div class="brkts-round-body[" ]/', $html_slice)) { // Brkts bracket format
      $bracketPlayers = parseBracketPlayers($html_slice);
      $players = $bracketPlayers['players'];
      $winner_count = $bracketPlayers['winner_count'];
      $bracket = createBracket($players, $winner_count);
    } else if (preg_match('/div class="bracket-column[" ]/', $html_slice)) { // Bracket format with DIVs
      $bracketPlayers = parseBracketPlayersOld($html_slice);
      $players = $bracketPlayers['players'];
      $winner_count = $bracketPlayers['winner_count'];
      $bracket = createBracketOld($players, $winner_count);
    }

    // debug($bracket);

    $brackets[] = $bracket;
  }
  return $brackets;
}


function parse_groups($html)
{
  global $debug;

  if (!preg_match_all('/<table\s+class="[^"]*?(?:prettytable|wikitable)?(?: grouptable)?"\s+style="width:\s*\d\d\dpx;margin:\s*0px;?">/', $html, $matches, PREG_OFFSET_CAPTURE, 5000)) {
    // echo "No groups found.";
    return array();
  }
  // debug($matches);

  $offsets = array();
  if (isset($matches[0][0][1])) {
    foreach ($matches[0] as $match) {
      $offsets[] = $match[1];
    }
  } else {
    $offsets[] = $matches[0][1];
  }

  // Offset end to Playoffs stage
  if (!preg_match('/class="(?:\w+-)bracket[ "]/', $html, $hit, PREG_OFFSET_CAPTURE, 10000)) {
    preg_match('/id="Playoffs"|id="Brackets?"|bgcolor="#f2f2f2">Finals/', $html, $hit, PREG_OFFSET_CAPTURE, 10000);
  }
  $offset_end = (isset($hit[0][1])) ? $hit[0][1] : strlen($html);

  $stage_offsets = array();
  if (preg_match_all('/id="[^"]+">\s*Group Stage \D*\d|<h2><[^>]+?id="[^"]+">[^<]*?Stage/i', $html, $hits, PREG_OFFSET_CAPTURE, 10000)) {
    if (isset($hits[0][0][1])) {
      foreach ($hits[0] as $hit) {
        $stage_offsets[] = $hit[1];
      }
    }
  }
  $stage = 0;

  for ($k = 0; $k < count($offsets); $k++) {

    $offset_start = $offsets[$k];
    if (isset($offsets[$k + 1])) {
      $html_slice = substr($html, $offset_start, $offsets[$k + 1] - $offset_start);
    } else {
      if ($offset_end < $offset_start) {
        if (preg_match('/class="bracket"/', $html, $hit, PREG_OFFSET_CAPTURE, $offset_start)) {
          $offset_end = (isset($hit[0][1])) ? $hit[0][1] : strlen($html);
        } else {
          $offset_end = strlen($html);
        }
      }
      $html_slice = substr($html, $offset_start, $offset_end - $offset_start);
      if (preg_match('/<\/table>[\s]*<\/div>[\s]*<\/div/', $html_slice, $hit, PREG_OFFSET_CAPTURE)) {
        $offset_end = (isset($hit[0][1])) ? $hit[0][1] : $offset_end;
        $html_slice = substr($html_slice, 0, $offset_end);
      }
    }

    // Make sure the group html slice ends before some next section
    if (preg_match('/<h[23]>/', $html_slice, $hit, PREG_OFFSET_CAPTURE)) {
      $html_slice = substr($html_slice, 0, $hit[0][1]);
    }

    // Initialize parameters
    $players = $player_names = $countries = $matches = $group = array();
    $group_name = $group_time = $group_time_local = null;
    $group_finished = true;

    // Read group info
    preg_match('/<th colspan="\d"[^>]*>.*Group (\w{1,2})\s*/', $html_slice, $hit);
    $group_name = (isset($hit[1])) ? trim($hit[1]) : $group_name;

    preg_match('/class="timer-object"[^>]*?>([^<]+)/', $html_slice, $hit);
    if (isset($hit[1])) {
      $group_time = trim($hit[1]);
      $group_time_local = strtotime(preg_replace('/[^\d\w,: ]+/', ' ', $group_time));
      $utc_diff = (date("I")) ? TIMEZONE + 1 : TIMEZONE;
      $group_time = strtotime($utc_diff . " hour", $group_time_local);
    }

    // Separate players and matches tables to make parsing easy
    $matchlist_offset = strpos($html_slice, 'matchlist');
    $players_html = substr($html_slice, 0, $matchlist_offset);
    $matches_html = substr($html_slice, $matchlist_offset);

    //Check for round-based results -- if found, skip to the last existing round
    if (preg_match_all('/<tr data-toggle-area-content="(\d+)"/', $players_html, $hits, PREG_OFFSET_CAPTURE)) {
      $round_offset = 0;
      for ($player_index = count($hits[1]) - 2; $player_index >= 0; $player_index--) {
        // Count down until we find the offset of the first item in the last round
        if ($hits[1][$player_index][0] == $hits[1][$player_index + 1][0]) {
          $round_offset = $hits[0][$player_index][1]; // Note we take offset from full match, index 0
        } else {
          break;
        }
      }
      $players_html = substr($players_html, $round_offset);
    }

    // Read each player row
    if (preg_match_all('/<tr [^>]*>/', $players_html, $hits, PREG_OFFSET_CAPTURE)) {
      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }

      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($players_html, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($players_html, $offset_start);
        }

        $table_end = strpos($html_slice_tmp, '</table>');
        if ($table_end !== false) {
          $html_slice_tmp = substr($html_slice_tmp, 0, $table_end);
        }

        $advance = $position = $country = $country_short = $race = $name = $match_score = $map_score = null;
        $offset = 0;

        $advance = preg_match('/<td [^>]*class="grouptableslot[^"]*bg-up|<tr class="[^"]*bg-up/', $html_slice_tmp);

        set_value($position, $offset, '/<th [^>]*>\s*(\d+)/', $html_slice_tmp);
        if ($group_finished && strlen($position) == 0) $group_finished = false;

        if (set_value($country, $offset, '/Category:([^"]+)"/', $html_slice_tmp)) {
          set_value($country_short, $offset, '/src="[^"]+?\/([\w]{2})(?:_hd)\.\w{3}"/', $html_slice_tmp);
          $country_short = strtolower($country_short);
          if ($country_short == 'uk') $country_short = 'gb';
        }

        set_value($race, $offset, '/title="(Protoss|Terran|Zerg|Random)"/', $html_slice_tmp);

        set_value($name, $offset, '/title="[^"]+"[^>]*>([^<]+)/', $html_slice_tmp);


        if (set_value($match_score, $offset, '/<b>([\d]+-[\d]+)/', $html_slice_tmp)) {
          $match_score = explode('-', $match_score);
          $match_score = array('win' => $match_score[0], 'loss' => $match_score[1]);
        }

        if (set_value($map_score, $offset, '/>([\d]+-[\d]+)/', $html_slice_tmp)) {
          $map_score = explode('-', $map_score);
          $map_score = array('win' => $map_score[0], 'loss' => $map_score[1]);
        }

        $player = array(
          'name' => trim($name),
          'race' => trim($race),
          'score' => array('match_score' => $match_score, 'map_score' => $map_score),
          'country' => trim($country),
          'country_short' => trim($country_short),
          'advance' => $advance,
          'position' => $position
        );

        $players[] = $player;
      }

      foreach ($players as $p) {
        $countries[] = strtolower($p['country']);
        $player_names[] = strtolower($p['name']);
      }
    }

    // Read each match row
    if (preg_match_all('/<tr[^>]*class="match-row ?[^"]*"[^>]*>/', $matches_html, $hits, PREG_OFFSET_CAPTURE)) {

      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }
      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($matches_html, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($matches_html, $offset_start);
        }

        $winner = $name1 = $name2 = $id1 = $id2 = $score1 = $score2 = null;
        $offset = 0;

        if (preg_match_all('/class="[^"]*matchlistslot\s*[^"]*"/', $html_slice_tmp, $matchlistslots)) {
          foreach ($matchlistslots[0] as $m_index => $m_value) {
            if (strpos($m_value, 'bg-win') !== false) {
              $winner = $m_index;
              break;
            }
          }
        }

        if (set_value($name1, $offset, '/<span[^>]*>([^<]*)/', $html_slice_tmp)) {
          $name1 = trim($name1);
          if (empty($name1)) {
            $name1 = EMPTY_NAME;
          } else if ($name1 != 'TBD') {
            foreach ($players as $i => $p) {
              if ($p['name'] == $name1) {
                $id1 = $i;
                break;
              }
            }
          }
        }

        set_value($score1, $offset, '/<td[^>]*?text-align:center[^>]*>[\s]*([\d]+)/', $html_slice_tmp);

        set_value($score2, $offset, '/<td[^>]*?text-align:center[^>]*>[\s]*([\d]+)/', $html_slice_tmp);

        if (set_value($name2, $offset, '/<span[^>]*>([^<]*)/', $html_slice_tmp)) {
          $name2 = trim($name2);
          if (empty($name2)) {
            $name2 = EMPTY_NAME;
          } else if ($name2 != 'TBD') {
            foreach ($players as $i => $p) {
              if ($p['name'] == $name2) {
                $id2 = $i;
                break;
              }
            }
          }
        }

        // BO1 situation, no scores, just winner
        if (strlen($score1) == 0 && strlen($score2) == 0 && 0 < strlen($winner)) {
          $score1 = ($winner === 0) ? 1 : 0;
          $score2 = ($winner === 1) ? 1 : 0;
        }

        if ($group_finished && strlen($winner) == 0) $group_finished = false;

        $match = array(
          'player1' => $id1,
          'player2' => $id2,
          'score1' => $score1,
          'score2' => $score2,
          'winner' => $winner
        );
        // debug($match);

        $matches[] = $match;
      }
    }

    // Read match rows from new matchlist
    if (preg_match_all('/<tr[^>]*class="brkts-matchlist-row[^"]*"[^>]*>/', $matches_html, $hits, PREG_OFFSET_CAPTURE)) {
      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }
      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($matches_html, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($matches_html, $offset_start);
        }

        $winner = $name1 = $name2 = $id1 = $id2 = $score1 = $score2 = null;
        $offset = 0;

        if (preg_match_all('/class="[^"]*brkts-opponent-hover\s*[^"]*"/', $html_slice_tmp, $matchlistslots)) {
          foreach ($matchlistslots[0] as $m_index => $m_value) {
            if (strpos($m_value, 'slot-winner') !== false) {
              $winner_index = ceil(count($matchlistslots[0]) / 2) <= $m_index ? 1 : 0;
              $winner = $winner_index;
              break;
            }
          }
        }

        if (preg_match_all('/<span[^>]*class="name"[^>]*>([^<]*)/', $html_slice_tmp, $names)) {

          if (count($names[1]) == 2) {
            $name1 = trim($names[1][0]);
            $name2 = trim($names[1][1]);
          } else if (count($names[1]) == 4) {
            $name1 = trim($names[1][0]);
            $name2 = trim($names[1][3]);
          }

          foreach (array(1, 2) as $name_index) {
            $name_var = "name" . $name_index;
            $id_var = "id" . $name_index;
            if (empty($$name_var)) {
              $$name_var = EMPTY_NAME;
            } else if ($$name_var != 'TBD') {
              foreach ($players as $i => $p) {
                if ($p['name'] == $$name_var) {
                  $$id_var = $i;
                  break;
                }
              }
            }
          }
        }

        set_value($score1, $offset, '/class="brkts-matchlist-score[^"]*"[^>]*>[\s]*([\d]+)/', $html_slice_tmp);

        set_value($score2, $offset, '/class="brkts-matchlist-score[^"]*"[^>]*>[\s]*([\d]+)/', $html_slice_tmp);

        // BO1 situation, no scores, just winner
        if (strlen($score1) == 0 && strlen($score2) == 0 && 0 < strlen($winner)) {
          $score1 = ($winner === 0) ? 1 : 0;
          $score2 = ($winner === 1) ? 1 : 0;
        }

        if ($group_finished && strlen($winner) == 0) $group_finished = false;

        $match = array(
          'player1' => $id1,
          'player2' => $id2,
          'score1' => $score1,
          'score2' => $score2,
          'winner' => $winner
        );
        // debug($match);

        $matches[] = $match;
      }
    }


    // Check if the group belongs to the next group stage
    if (!empty($groups) && !empty($stage_offsets) && isset($stage_offsets[$stage + 1]) && $stage_offsets[$stage + 1] < $offsets[$k]) $stage++;

    $group = array(
      'name' => $group_name,
      'time' => $group_time,
      'time_local' => $group_time_local,
      'finished' => $group_finished,
      'players' => $players,
      'countries' => $countries,
      'player_names' => $player_names,
      'matches' => $matches,
      'crosstable' => null
    );

    $groups[$stage][] = $group;
  }

  if (!empty($groups)) {
    // Read crosstables if exists, and assign to groups in linear order
    $crosstables = parse_crosstables($html);
    if (!empty($crosstables)) {
      for ($i = 0; $i < count($groups); $i++) {
        if (empty($crosstables)) break;
        for ($j = 0; $j < count($groups[$i]); $j++) {
          if (empty($crosstables)) break;
          $groups[$i][$j]['crosstable'] = array_shift($crosstables);
          $groups[$i][$j]['time'] = $groups[$i][$j]['crosstable']['time'];
          $groups[$i][$j]['time_local'] = $groups[$i][$j]['crosstable']['time_local'];
        }
      }
    }
  }

  // debug($groups);
  return $groups;
}


function parse_crosstables($html)
{
  if (!preg_match_all('/<table class="[^"]*?crosstable/', $html, $matches, PREG_OFFSET_CAPTURE, 5000)) {
    return array();
  }

  $offsets = array();
  if (isset($matches[0][0][1])) {
    foreach ($matches[0] as $match) {
      $offsets[] = $match[1];
    }
  } else {
    $offsets[] = $matches[0][1];
  }

  // Offset end to Playoffs stage
  if (!preg_match('/class="bracket[ "]/', $html, $hit, PREG_OFFSET_CAPTURE, 10000)) {
    preg_match('/id="Playoffs"|id="Brackets?"|bgcolor="#f2f2f2">Finals|class="[^"]*?bracket-wrapper/', $html, $hit, PREG_OFFSET_CAPTURE, 10000);
  }
  $offset_end = (isset($hit[0][1])) ? $hit[0][1] : strlen($html);

  for ($k = 0; $k < count($offsets); $k++) {

    $offset_start = $offsets[$k];
    if (isset($offsets[$k + 1])) {
      $html_slice = substr($html, $offset_start, $offsets[$k + 1] - $offset_start);
    } else {
      if ($offset_end < $offset_start) {
        if (preg_match('/class="bracket"/', $html, $hit, PREG_OFFSET_CAPTURE, $offset_start)) {
          $offset_end = (isset($hit[0][1])) ? $hit[0][1] : strlen($html);
        } else {
          $offset_end = strlen($html);
        }
      }
      $html_slice = substr($html, $offset_start, $offset_end - $offset_start);
    }

    if (preg_match('/<\/table>[\s]*<\/div>/', $html_slice, $hit, PREG_OFFSET_CAPTURE)) {
      $offset_end = (isset($hit[0][1])) ? $hit[0][1] : $offset_end;
      $html_slice = substr($html_slice, 0, $offset_end + strlen('<\/table>'));
    }

    $players = $player_names = $countries = $matches = $printtable = $crosstable = array();
    $match_time = $match_time_local = null;
    $crosstable_time = $crosstable_time_local = null;
    $crosstable_finished = true;

    // Get just the last row of table, which includes the players
    $html_last_row = '';
    if (preg_match_all('/<tr[^>]*>/', $html_slice, $hits, PREG_OFFSET_CAPTURE)) {
      $html_last_row = substr($html_slice, $hits[0][count($hits[0]) - 1][1]);
    }

    // Read each player
    if (preg_match_all('/<th[^>]*>.+?\/th>/', $html_last_row, $hits, PREG_OFFSET_CAPTURE)) {
      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }

      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($html_last_row, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($html_last_row, $offset_start);
        }

        $country = $country_short = $race = $name = null;
        $offset = 0;

        if (set_value($country, $offset, '/Category:([^"]+)"/', $html_slice_tmp)) {
          set_value($country_short, $offset, '/src="[^"]+?\/([\w]{2})(?:_hd)\.\w{3}"/', $html_slice_tmp);
          $country_short = strtolower($country_short);
          if ($country_short == 'uk') $country_short = 'gb';
        }

        set_value($race, $offset, '/title="(Protoss|Terran|Zerg|Random)"/', $html_slice_tmp);

        set_value($name, $offset, '/title="[^"]+"[^>]*>([^<]+)/', $html_slice_tmp);

        if (!empty($name)) {
          $player = array(
            'name' => trim($name),
            'race' => trim($race),
            'country' => trim($country),
            'country_short' => trim($country_short)
          );

          $players[] = $player;
        }
      }

      foreach ($players as $p) {
        $countries[] = strtolower($p['country']);
        $player_names[] = strtolower($p['name']);
      }
    }

    //Initialize print table
    for ($i = 0; $i < count($players); $i++) {
      $printtable[$i][0] = $players[$i];
      $printtable[count($players)][$i + 1] = $players[$i];
      for ($j = 1; $j <= count($players); $j++) {
        if ($i + 1 == $j) {
          $printtable[$i][$j] = array();
        } else {
          $printtable[$i][$j] = array('score1' => 0, 'score2' => 0); //populate these with matches later
        }
      }
    }
    $printtable[count($players)][0] = array();


    // Read all matches
    if (preg_match_all('/<td class="[^"]+?bracket-game/', $html_slice, $hits, PREG_OFFSET_CAPTURE)) {
      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }

      $rowid = $cellid = 0;

      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($html_slice, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($html_slice, $offset_start);
        }

        $cell_end = strpos($html_slice_tmp, '</td>');
        if ($cell_end !== false) {
          $html_slice_tmp = substr($html_slice_tmp, 0, $cell_end);
        }

        $winner = $name1 = $name2 = $id1 = $id2 = $score1 = $score2 = $date = null;
        $offset = 0;

        set_value($score1, $offset, '/>\s*(\d)/', $html_slice_tmp);

        set_value($score2, $offset, '/-(\d)\s*</', $html_slice_tmp);

        if (set_value($name1, $offset, '/<div class="bracket-popup-header-left">([^<]*)/', $html_slice_tmp)) {
          $name1 = trim($name1);
          $name1 = str_replace(array('&nbsp;', '&#160;'), '', $name1);
          if (empty($name1)) {
            $name1 = EMPTY_NAME;
          } else if ($name1 != 'TBD') {
            foreach ($players as $i => $p) {
              if ($p['name'] == $name1) {
                $id1 = $i;
                break;
              }
            }
          }

          if (set_value($name2, $offset, '/<div class="bracket-popup-header-right">.+?<\/a>([^<]+)/', $html_slice_tmp)) {
            $name2 = trim($name2);
            $name2 = str_replace(array('&nbsp;', '&#160;'), '', $name2);
            if (empty($name2)) {
              $name2 = EMPTY_NAME;
            } else if ($name2 != 'TBD') {
              foreach ($players as $i => $p) {
                if ($p['name'] == $name2) {
                  $id2 = $i;
                  break;
                }
              }
            }
          }

          if (preg_match('/class="datetime">([^<]+).*?UTC(.\d+)/', $html_slice_tmp, $hit)) {
            $match_time = trim($hit[1]);
            $match_time = str_replace(' - ', ', ', $match_time);
            $match_time = $match_time_local = strtotime(preg_replace('/[^\d\w,: ]+/', ' ', $match_time));
            $utc_diff = (date("I")) ? TIMEZONE + 1 - $hit[2] : TIMEZONE - $hit[2];
            $match_time = strtotime($utc_diff . " hour", $match_time);

            if (empty($crosstable_time) || $match_time < $crosstable_time) {
              $crosstable_time = $match_time;
              $crosstable_time_local = $match_time_local;
            }
          }
        }

        // If player names not found, deduce them from the table indexes
        if ($id1 == null) {
          if ($cellid == $rowid) $cellid++;
          if (count($players) <= $cellid) {
            $rowid++;
            $cellid = 0;
          }
          $id1 = $rowid;
          $id2 = $cellid;
          $cellid++;
        }

        if (0 < $score1 || 0 < $score2) {
          $winner = ($score1 < $score2) ? 1 : 0;
        }

        if ($crosstable_finished && strlen($winner) == 0) $crosstable_finished = false;

        $match = array(
          'player1' => $id1,
          'player2' => $id2,
          'score1' => $score1,
          'score2' => $score2,
          'winner' => $winner,
          'time' => $match_time,
          'time_local' => $match_time_local
        );

        //Check if match already exists, and add to array if not
        $match_exists = false;
        if (!empty($matches)) {
          foreach ($matches as $m) {
            if (($m['player1'] == $match['player1'] && $m['player2'] == $match['player2']) || ($m['player1'] == $match['player2'] && $m['player2'] == $match['player1'])) {
              $matches[] = $match;
              break;
            }
          }
        }

        $printtable[$id1][$id2 + 1] = $match;
      }
    }

    $crosstable = array(
      'time' => $crosstable_time,
      'time_local' => $crosstable_time_local,
      'finished' => $crosstable_finished,
      'players' => $players,
      'countries' => $countries,
      'player_names' => $player_names,
      'matches' => $matches,
      'table' => $printtable
    );

    $crosstables[] = $crosstable;
  }

  return $crosstables;
}


function round_name($round, $has_lower_bracket)
{
  $prefix = $has_lower_bracket ? 'UB' : '';
  switch ($round) {
    case 2:
      return $prefix . ' ' . FINAL_MATCH;
    case 4:
      return $prefix . ' ' . SEMIFINAL_MATCH;
    case 'bronze':
      return BRONZE_MATCH;
    case 'grand_final':
      return GRAND_FINAL;
  }
  return $prefix . ' ' . "RO" . $round;
}


function race_icon($race)
{
  switch ($race) {
    case 'Protoss':
      return SMILIES_DIR . IMG_PROTOSS;
    case 'Terran':
      return SMILIES_DIR . IMG_TERRAN;
    case 'Zerg':
      return SMILIES_DIR . IMG_ZERG;
    case 'Random':
      return SMILIES_DIR . IMG_RANDOM;
  }
  return '';
}


function set_value(&$value, &$offset, $pattern, $string)
{
  if (preg_match($pattern, $string, $matches, PREG_OFFSET_CAPTURE, $offset)) {
    $value = $matches[1][0];
    $offset = $matches[1][1] + strlen($value);
    return true;
  }
  return false;
}


function get_with_curl($url)
{
  $curl = curl_init();

  // Setup headers - I used the same headers from Firefox version 2.0.0.6
  // below was split up because php.net said the line was too long. :/
  $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
  $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
  $header[] = "Cache-Control: max-age=0";
  $header[] = "Connection: keep-alive";
  $header[] = "Keep-Alive: 300";
  $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
  $header[] = "Accept-Language: en-us,en;q=0.5";
  $header[] = "Pragma: ";
  // browsers keep this blank.

  $referers = array("google.com", "yahoo.com", "msn.com", "ask.com", "live.com");
  $choice = array_rand($referers);
  $referer = "http://" . $referers[$choice] . "";

  $browsers = array("Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.3) Gecko/2008092510 Ubuntu/8.04 (hardy) Firefox/3.0.3", "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1) Gecko/20060918 Firefox/2.0", "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.3) Gecko/2008092417 Firefox/3.0.3", "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; Media Center PC 5.0; .NET CLR 3.0.04506)");
  $choice2 = array_rand($browsers);
  $browser = $browsers[$choice2];

  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_USERAGENT, $browser);
  curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
  curl_setopt($curl, CURLOPT_REFERER, $referer);
  curl_setopt($curl, CURLOPT_AUTOREFERER, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_TIMEOUT, 10);
  curl_setopt($curl, CURLOPT_MAXREDIRS, 7);
  // curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

  $data = curl_exec($curl);

  if ($data === false) {
    $data = curl_error($curl);
  }

  // execute the curl command
  curl_close($curl);
  // close the connection

  return $data;
  // and finally, return $html
}


function mylog($data)
{
  if (LOG === true) {
    $s = date('Y-m-d H:i:s') . ", " . $data['title'];
    $s .= ", Groups " . $data['group_count'];
    $s .= ", Brackets " . $data['bracket_count'];
    $time = secondsToTime($data['cache_dur']);
    $cache = array();
    if (0 < $time['d']) $cache[] = $time['d'] . "d";
    if (0 < $time['h']) $cache[] = $time['h'] . "h";
    if (0 < $time['m']) $cache[] = $time['m'] . "m";
    if (0 < $time['s']) $cache[] = $time['s'] . "s";
    $s .= ", Cache " . join(" ", $cache);
    $s .= ", GET[";
    foreach ($data['get_vars'] as $k => $v) {
      $s .= $k . "=" . $v . " | ";
    }
    $s = substr($s, 0, -3); // remove last " | "
    $s .= "]";
    @file_put_contents(LOGFILE, $s . "\r\n", FILE_APPEND);
  }
}


function secondsToTime($inputSeconds)
{

  $secondsInAMinute = 60;
  $secondsInAnHour  = 60 * $secondsInAMinute;
  $secondsInADay    = 24 * $secondsInAnHour;

  // extract days
  $days = floor($inputSeconds / $secondsInADay);

  // extract hours
  $hourSeconds = $inputSeconds % $secondsInADay;
  $hours = floor($hourSeconds / $secondsInAnHour);

  // extract minutes
  $minuteSeconds = $hourSeconds % $secondsInAnHour;
  $minutes = floor($minuteSeconds / $secondsInAMinute);

  // extract the remaining seconds
  $remainingSeconds = $minuteSeconds % $secondsInAMinute;
  $seconds = ceil($remainingSeconds);

  // return the final array
  $obj = array(
    'd' => (int) $days,
    'h' => (int) $hours,
    'm' => (int) $minutes,
    's' => (int) $seconds,
  );
  return $obj;
}


function sort_by_name($a, $b)
{
  if ($a['name'] == $b['name']) {
    return 0;
  }
  return ($a['name'] < $b['name']) ? -1 : 1;
}


function sort_by_time($a, $b)
{
  if ($a['time'] == $b['time']) {
    return 0;
  }
  return ($a['time'] < $b['time']) ? -1 : 1;
}


function debug($value, $die = true)
{
  print_r($value);
  if ($die) {
    die;
  }
}


function double_elim_max_round_of($match_count)
{
  for ($i = 2; $i < $match_count; $i++) {
    if ((pow(2, $i) - 1) * 2 == $match_count) {
      return pow(2, $i);
    }
  }
  return 0;
}

function parseBracketPlayers($html)
{
  $pattern = '/brkts-opponent-entry[ "]{1}[^>]+>[\s\S]*?brkts-opponent-score-inner[^>]+>[\s\S]*?<\/div>/i';
  preg_match_all($pattern, $html, $matches);
  // debug($matches);

  $players = array();
  $winner_count = 0;
  for ($i = 0; $i < count($matches[0]); $i++) {
    $match = $matches[0][$i];

    $country = '';
    $country_short = '';
    $race = '';
    $name = EMPTY_NAME;
    $score = '';
    $winner = false;

    preg_match('/brkts-opponent-entry-left [^"]*(Terran|Protoss|Zerg|Random)/i', $match, $hit);
    $race = isset($hit[1]) ? $hit[1] : $race;
    preg_match('/src="[^"]+?\/([\w]{2})(?:_hd)\.\w{3}"/', $match, $hit);
    $country_short = (isset($hit[1])) ? trim(strtolower($hit[1])) : $country_short;
    preg_match('/<img [^>]*?title="([^"]+)"/', $match, $hit);
    $country = (isset($hit[1])) ? trim($hit[1]) : $country;
    if (preg_match('/brkts-opponent-win/', $match)) {
      $winner = true;
      $winner_count++;
    }
    preg_match('/<span class="name"[^>]*>([^<]+)/', $match, $hit);
    $name = (isset($hit[1]) && !empty($hit[1])) ? trim($hit[1]) : $name;
    preg_match('/brkts-opponent-score-inner[^>]+>(?:<b>)?([\d]+)/', $match, $hit);
    $score = (isset($hit[1])) ? $hit[1] : $score;

    if ($country_short == 'uk') $country_short = 'gb';
    if ($name == 'TBD' || $name == '&nbsp;' || $name == '&#160;') $name = EMPTY_NAME;

    $player = array(
      'name' => $name,
      'race' => $race,
      'score' => $score,
      'country' => $country,
      'country_short' => $country_short,
      'winner' => $winner
    );
    $players[] = $player;
  }

  return array('players' => $players, 'winner_count' => $winner_count);
}

function createBracket($players, $winner_count)
{
  $players_count = count($players);
  if ($players_count < 0) {
    return array();
  }

  $bracket_finished = true;
  $lb_rounds = array();

  $bronze_match = array();
  $lb_players = array();
  $grand_final = array();

  if ($max_round_of = double_elim_max_round_of($players_count / 2)) {
    $lb_max_round_of = $max_round_of / 2;
    $lb_players = array_splice($players, $players_count / 2);
    $gf_players = array_splice($lb_players, -2);
    $grand_final = array('player1' => $gf_players[0], 'player2' => $gf_players[1]);
  } else if (count($players) % 8 == 0) { // bronze match
    $max_round_of = (count($players) / 2);
    $bronze_players = array_splice($players, -2);
    $bronze_match = array('player1' => $bronze_players[0], 'player2' => $bronze_players[1]);
  } else {
    $max_round_of = ($players_count / 2) + 1;
  }

  $bracket_finished = ($bracket_finished && $players_count / 2 <= $winner_count);

  $round_of = $max_round_of;
  $start_match = 0;

  // skip players until first 2^N round is found
  if (!preg_match("/^\d+$/", log($round_of, 2))) {
    if ($round_of != 12) { //ro12 is an exception for now
      $round_of = pow(2, floor(log($round_of, 2)));
      $start_match = ($max_round_of - $round_of) * 2;
    }
  }

  /*
  matches order in bracket tree:
  1
      3
  2
          7
  4
      6
  5
  */

  $match_counter = 0;
  $reset_counter = 0;
  for ($i = $start_match; $i < count($players) - 1; $i += 2) {
    if ($match_counter == 2) {
      $round_of /= 2;
      $match_counter = -1;
    } else if ($match_counter == 0 && $round_of < $max_round_of) {
      if ($reset_counter == 1) {
        $round_of /= 2;
        $match_counter = 0;
        $reset_counter++;
      } else if ($reset_counter == 3) {
        $match_counter = 0;
        $reset_counter = 0;
        $round_of *= 4;
      } else {
        $round_of *= 2;
        $reset_counter++;
      }
    }
    $rounds[$round_of][] = array('player1' => $players[$i], 'player2' => $players[$i + 1]);
    $match_counter++;
  }

  if ($lb_players) {
    $round_counter = 0;
    $round_of = $lb_max_round_of;
    $stage = 1;
    for ($i = 0; $i < count($lb_players) - 1; $i += 2) {
      $lb_rounds[$round_of][$stage][] = array('player1' => $lb_players[$i], 'player2' => $lb_players[$i + 1]);
      $round_counter += 2;
      if (pow(2, floor(log($round_of, 2))) - 1 <= $round_counter) {
        if ($stage == 2) {
          $round_of = pow(2, ceil(log($round_of, 2))) / 2;
          $round_counter = 0;
          $stage = 1;
        } else {
          $stage = 2;
          $round_counter = 0;
        }
      }
    }
  }

  if (!empty($bronze_match)) $rounds['bronze'][] = $bronze_match;
  if (!empty($grand_final)) $rounds['grand_final'][] = $grand_final;

  $bracket = array(
    'rounds' => $rounds,
    'lb_rounds' => $lb_rounds,
    'finished' =>  $bracket_finished
  );

  return $bracket;
}

function parseBracketPlayersOld($html)
{
  $pattern = '/bracket-cell-[^>]+>[\s\S]*?bracket-score[^>]+>[^<]*/i';
  preg_match_all($pattern, $html, $matches);
  // debug($matches);

  $players =  array();
  $winner_count = 0;
  for ($i = 0; $i < count($matches[0]); $i++) {
    $match = $matches[0][$i];

    $country = '';
    $country_short = '';
    $race = '';
    $name = EMPTY_NAME;
    $score = '';
    $winner = false;

    preg_match('/background:rgb\(([^,]+),\s*([^,]+),\s*([^\)]+)/', $match, $hit);
    if (count($hit) > 2) {
      $colors = $hit[1] . ',' . $hit[2] . ',' . $hit[3];
      switch ($colors) {
        case '251,223,223':
          $race = 'Zerg';
          break;
        case '222,227,239':
          $race = 'Terran';
          break;
        case '221,244,221':
          $race = 'Protoss';
          break;
      }
      if ($hit[1] == 'DDDDDD') $name = BYE_NAME;
    }

    preg_match('/src="[^"]+?\/([\w]{2})(?:_hd)\.\w{3}"/', $match, $hit);
    $country_short = (isset($hit[1])) ? trim(strtolower($hit[1])) : $country_short;
    preg_match('/title="([^"]+)"/', $match, $hit);
    $country = (isset($hit[1])) ? trim($hit[1]) : $country;
    if (preg_match('/font-weight:bold/', $match)) {
      $winner = true;
      $winner_count++;
    }
    preg_match('/<span[^>]+>([^<]+)/', $match, $hit);
    $name = (isset($hit[1]) && !empty($hit[1])) ? trim($hit[1]) : $name;
    preg_match('/bracket-score[^>]+>([\d]+)/', $match, $hit);
    $score = (isset($hit[1])) ? $hit[1] : $score;

    if ($country_short == 'uk') $country_short = 'gb';
    if ($name == 'TBD') $name = EMPTY_NAME;

    $player = array(
      'name' => $name,
      'race' => $race,
      'score' => $score,
      'country' => $country,
      'country_short' => $country_short,
      'winner' => $winner
    );
    $players[] = $player;
  }
  return array('players' => $players, 'winner_count' => $winner_count);
}

function createBracketOld($players, $winner_count = 0)
{
  $players_count = count($players);
  if ($players_count < 0) {
    return array();
  }

  $bracket_finished = true;
  $lb_rounds = array();

  $bronze_match = array();
  $lb_players = array();
  $grand_final = array();

  if ($max_round_of = double_elim_max_round_of($players_count / 2)) {
    $lb_max_round_of = $max_round_of / 2;
    $lb_players = array_splice($players, $players_count / 2);
    $gf_players = array_splice($lb_players, -2);
    $grand_final = array('player1' => $gf_players[0], 'player2' => $gf_players[1]);
  } else if (count($players) % 8 == 0) { // bronze match
    $max_round_of = (count($players) / 2);
    $bronze_players = array_splice($players, -2);
    $bronze_match = array('player1' => $bronze_players[0], 'player2' => $bronze_players[1]);
  } else {
    $max_round_of = ($players_count / 2) + 1;
  }

  $bracket_finished = ($bracket_finished && $players_count / 2 <= $winner_count);

  $round_of = $max_round_of;
  $round_counter = 0;
  $start_match = 0;

  // skip players until first 2^N round is found
  if (!preg_match("/^\d+$/", log($round_of, 2))) {
    if ($round_of != 12) { //ro12 is an exception for now
      $round_of = pow(2, floor(log($round_of, 2)));
      $start_match = ($max_round_of - $round_of) * 2;
    }
  }

  for ($i = $start_match; $i < count($players) - 1; $i += 2) {
    $rounds[$round_of][] = array('player1' => $players[$i], 'player2' => $players[$i + 1]);
    $round_counter += 2;
    if (pow(2, floor(log($round_of, 2))) - 1 <= $round_counter) {
      $round_of = pow(2, ceil(log($round_of, 2))) / 2;
      $round_counter = 0;
    }
  }

  if ($lb_players) {
    $round_of = $lb_max_round_of;
    $stage = 1;
    for ($i = 0; $i < count($lb_players) - 1; $i += 2) {
      $lb_rounds[$round_of][$stage][] = array('player1' => $lb_players[$i], 'player2' => $lb_players[$i + 1]);
      $round_counter += 2;
      if (pow(2, floor(log($round_of, 2))) - 1 <= $round_counter) {
        if ($stage == 2) {
          $round_of = pow(2, ceil(log($round_of, 2))) / 2;
          $round_counter = 0;
          $stage = 1;
        } else {
          $stage = 2;
          $round_counter = 0;
        }
      }
    }
  }

  if (!empty($bronze_match)) $rounds['bronze'][] = $bronze_match;
  if (!empty($grand_final)) $rounds['grand_final'][] = $grand_final;

  $bracket = array(
    'rounds' => $rounds,
    'lb_rounds' => $lb_rounds,
    'finished' =>  $bracket_finished
  );

  return $bracket;
}
