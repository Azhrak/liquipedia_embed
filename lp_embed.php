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
  print_r($embeds);
  die;
}


// PRINT BRACKET
if ($mode == 'bracket') {
  if (isset($embeds['brackets'])) $brackets = $embeds['brackets'];
  if (empty($brackets)) die(BRACKET_NOT_FOUND);
  $bracket = (isset($brackets[$bracket_number - 1])) ? $brackets[$bracket_number - 1] : $brackets[0];
  // print_r($bracket);die;
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
    <?php
    $round_count = 1;
    $prev_round = null;
    ?>
    <?php if (!empty($bracket)) : ?>
      <div class="forum_bracket <?php echo 'forum_bracket_size_' . count($bracket['rounds']); ?> <?php echo 'forum_bracket_ro_' . array_keys($bracket['rounds'])[0]; ?>">
        <?php foreach ($bracket['rounds'] as $round => $matches) : ?>
          <?php if (!empty($ro_start) && $round != 'bronze' && $ro_start < $round) continue; ?>
          <?php if (!empty($ro_end) && $round != 'bronze' && $round < $ro_end) continue; ?>

          <?php if (empty($prev_round)) : ?>
            <div class="forum_bracket_round forum_bracket_round_<?php echo $round_count++; ?>">
              <div class="forum_bracket_round_name">
                <?php echo round_name($round) ?>
              </div>
              <?php $prev_round = $round; ?>
            <?php elseif ($round == 'bronze') : ?>
              <div class="forum_bracket_bronze_<?php echo $round_count ?>">
                <div class="forum_bracket_round_name">
                  <?php echo round_name($round) ?>
                </div>
              <?php elseif ($round != $prev_round) : ?>
              </div>
              <?php $last_round = ($round == 2) ? 'forum_bracket_round_last' : '' ?>
              <div class="forum_bracket_round forum_bracket_round_<?php echo $round_count++ . ' ' . $last_round ?>">
                <div class="forum_bracket_round_name">
                  <?php echo round_name($round) ?>
                </div>
              <?php else :
              $prev_round = $round;
            endif; ?>

              <?php foreach ($matches as $i => $match) : ?>
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
            <?php elseif ($i < count($matches) - 1 && $i % 2 == 0) : ?>
              <div class="forum_bracket_line_join"></div>
              <div class="forum_bracket_line_vertical"></div>
            <?php endif; ?>
          <?php endforeach; ?>

        <?php endforeach; ?>
            </div>
      </div>
      <div style="clear:both"></div>
    <?php endif; ?>
  </body>

  </html>


<?php }
// PRINT GROUPS
elseif ($mode == 'group') {
  if (isset($embeds['groups'])) $groups = $embeds['groups'];
  // print_r($groups);die;
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
function parse_brackets($html)
{
  global $debug;

  if (!preg_match_all('/class="bracket(?:"| [^"]+")/', $html, $matches, PREG_OFFSET_CAPTURE, 10000)) {
    if (!preg_match('/id="Playoffs"|id="Brackets?"|bgcolor="#f2f2f2">Finals/', $html, $matches, PREG_OFFSET_CAPTURE, 10000)) {
      // echo "Error: No bracket found.";
      return array();
    }
  }

  $offsets = array();
  if (isset($matches[0][0][1])) {
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

    $bracket_finished = true;

    if (preg_match('/div class="bracket-column"/', $html_slice)) { // New bracket format, with DIVs

      $pattern = '/bracket-cell-[^>]+>[\s\S]*?bracket-score[^>]+>[^<]*/i';
      preg_match_all($pattern, $html_slice, $matches);
      // print_r($matches);die;

      $players = $rounds = $bracket = array();
      $winner_count = 0;
      for ($i = 0; $i < count($matches[0]); $i++) {
        $match = $matches[0][$i];

        $country = '';
        $country_short = '';
        $race = '';
        $name = EMPTY_NAME;
        $score = '';
        $winner = false;

        preg_match('/background:([^;]+)/', $match, $hit);
        if (isset($hit[1])) {
          switch ($hit[1]) {
            case '#F2B8B8':
            case 'rgb(242,184,184)':
            case 'rgb(246.8,204.8,204.8)':
            case 'rgb(251,223,223)':
              $race = 'Zerg';
              break;
            case '#B8B8F2':
            case 'rgb(184,184,242)':
            case 'rgb(204.26666666667,206.93333333333,240.4)':
            case 'rgb(222,227,239)':
              $race = 'Terran';
              break;
            case '#B8F2B8':
            case 'rgb(184,242,184)':
            case 'rgb(203.73333333333,243.06666666667,203.73333333333)':
            case 'rgb(221,244,221)':
              $race = 'Protoss';
              break;
          }
          if ($hit[1] == 'DDDDDD') $name = BYE_NAME;
        }

        // if (!empty($race)) {
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
        // }

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
      // print_r($players);die;
      // print_r($scores);die;

      $bronze_match = array();
      if (count($players) % 8 == 0) { // bronze match
        $max_round_of = (count($players) / 2);
        $player1 = $players[count($players) - 2];
        $player2 = $players[count($players) - 1];
        unset($players[count($players) - 2]);
        unset($players[count($players) - 1]);
        $players = array_slice($players, 0);
        $bronze_match = array('player1' => $player1, 'player2' => $player2);
      } else {
        $max_round_of = (count($players) / 2) + 1;
      }

      $bracket_finished = ($bracket_finished && count($players) / 2 <= $winner_count);

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
    } else { // Old bracket format, with TABLE

      $pattern = '/bgcolor="#?(F2B8B8|B8F2B8|B8B8F2|DDDDDD|F2F2F2|)" rowspan="2"[^>]*>([^<]*)(?:(?:<[^>]*>){3}&#160;(?:<b>)?[^<]+)?/i';
      preg_match_all($pattern, $html_slice, $matches);
      // print_r($matches);die;

      $players = $scores = $rounds = $bracket = array();
      for ($i = 0; $i < count($matches[0]); $i++) {
        $match = $matches[0][$i];
        $color = $matches[1][$i];

        if (strtoupper($color) == 'F2F2F2') { // score cell
          $score = '';
          preg_match('/>[\s]*([\d]+)[\s]*$/', $match, $hit);
          $score = (isset($hit[1])) ? $hit[1] : $score;
          $scores[] = $score;
          if ($bracket_finished && $score == '') $bracket_finished = false;
        } else { // player cell
          $country = '';
          $country_short = '';
          $race = '';
          $name = EMPTY_NAME;
          $winner = false;

          switch ($color) {
            case 'F2B8B8':
              $race = 'Zerg';
              break;
            case 'B8B8F2':
              $race = 'Terran';
              break;
            case 'B8F2B8':
              $race = 'Protoss';
              break;
          }
          if ($color == 'DDDDDD') $name = BYE_NAME;
          if (!empty($race)) {
            preg_match('/src="[^"]+?\/([\w]{2})\.[\w]{3,4}"/', $match, $hit);
            $country_short = (isset($hit[1])) ? trim(strtolower($hit[1])) : $country_short;
            preg_match('/title="([^"]+)"/', $match, $hit);
            $country = (isset($hit[1])) ? trim($hit[1]) : $country;
            preg_match('/(?:&#160;)+(?:(<b>))?([^<]+)[\s]*$/', $match, $hit);
            $winner = (isset($hit[1]) && !empty($hit[1]));
            $name = (isset($hit[2]) && !empty($hit[2])) ? trim($hit[2]) : $name;
          }

          if ($country_short == 'uk') $country_short = 'gb';
          if ($name == 'TBD') $name = EMPTY_NAME;

          $player = array(
            'name' => $name,
            'race' => $race,
            'country' => $country,
            'country_short' => $country_short,
            'winner' => $winner
          );
          $players[] = $player;
          if ($bracket_finished && $name == EMPTY_NAME) $bracket_finished = false;
        }
      }
      // print_r($players);die;
      // print_r($scores);die;

      $bronze_match = array();
      if (count($players) % 8 == 0) { // bronze match
        $max_round_of = (count($players) / 2);
        $i = (count($players) / 2) - 1 + floor(count($players) / 3);
        $player1 = $players[$i];
        $player2 = $players[$i + 2];
        $player1['score'] = $scores[$i];
        $player2['score'] = $scores[$i + 2];
        unset($players[$i]);
        unset($players[$i + 2]);
        unset($scores[$i]);
        unset($scores[$i + 2]);
        $players = array_slice($players, 0);
        $scores = array_slice($scores, 0);
        $bronze_match = array('player1' => $player1, 'player2' => $player2);
      } else {
        $max_round_of = (count($players) / 2) + 1;
      }

      $start_i = 0;
      $step = 4;
      for ($match_count = ($max_round_of / 2); $match_count >= 1; $match_count = $match_count / 2) {
        for ($i = $start_i; $i < count($players) - 1; $i += $step) {
          $player1 = $players[$i];
          $player2 = $players[$i + 1];
          $player1['score'] = $scores[$i];
          $player2['score'] = $scores[$i + 1];
          $rounds[$match_count * 2][] = array('player1' => $player1, 'player2' => $player2);
        }
        $start_i = $start_i + ($step / 2);
        $step *= 2;
      }
    }

    if (!empty($bronze_match)) $rounds['bronze'][] = $bronze_match;

    $bracket = array(
      'rounds' => $rounds,
      'finished' =>  $bracket_finished
    );

    // print_r($bracket);die;

    $brackets[] = $bracket;
  }
  return $brackets;
}


function parse_groups($html)
{
  global $debug;

  if (!preg_match_all('/<table class="[^"]*?(?:prettytable|wikitable)?(?: grouptable)?" style="width: \d\d\dpx;margin: 0px;">/', $html, $matches, PREG_OFFSET_CAPTURE, 5000)) {
    // echo "No groups found.";
    return array();
  }
  // print_r($matches);die;

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
      if (preg_match('/<\/table>[\s]*<\/div>[\s]*<\/?div/', $html_slice, $hit, PREG_OFFSET_CAPTURE)) {
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
    preg_match('/<th colspan="\d">.*Group (\w{1,2})\s*/', $html_slice, $hit);
    $group_name = (isset($hit[1])) ? trim($hit[1]) : $group_name;

    preg_match('/class="timer-object"[^>]*>([^<]+)<[^>]+UTC(.\d+)/', $html_slice, $hit);
    if (isset($hit[1])) {
      $group_time = trim($hit[1]);
      $group_time = $group_time_local = strtotime(preg_replace('/[^\d\w,: ]+/', ' ', $group_time));
      $utc_diff = (date("I")) ? TIMEZONE + 1 - $hit[2] : TIMEZONE - $hit[2];
      $group_time = strtotime($utc_diff . " hour", $group_time);
    }

    // Read each player row
    if (preg_match_all('/<tr[^>]*>[\s]*<th style="width: 16px[^"]+">/', $html_slice, $hits, PREG_OFFSET_CAPTURE)) {
      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }

      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($html_slice, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($html_slice, $offset_start);
        }

        $table_end = strpos($html_slice_tmp, '</table>');
        if ($table_end !== false) {
          $html_slice_tmp = substr($html_slice_tmp, 0, $table_end);
        }

        $advance = $position = $country = $country_short = $race = $name = $match_score = $map_score = null;
        $offset = 0;

        $advance = preg_match('/color:#cfc;/', $html_slice_tmp);

        set_value($position, $offset, '/width: 16px[^"]+">[\s]*(\d)/', $html_slice_tmp);
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
    if (preg_match_all('/<tr[^>]*>[\s]*<td class="matchlistslot" style="width:[^"]+;text-align:right/', $html_slice, $hits, PREG_OFFSET_CAPTURE)) {

      $offsets_tmp = array();
      foreach ($hits[0] as $hit) {
        $offsets_tmp[] = $hit[1];
      }
      for ($j = 0; $j < count($offsets_tmp); $j++) {
        $offset_start = $offsets_tmp[$j];
        if (isset($offsets_tmp[$j + 1])) {
          $html_slice_tmp = substr($html_slice, $offset_start, $offsets_tmp[$j + 1] - $offset_start);
        } else {
          $html_slice_tmp = substr($html_slice, $offset_start);
        }

        $winner = $name1 = $name2 = $id1 = $id2 = $score1 = $score2 = null;
        $offset = 0;

        $winner = (preg_match('/style="width:[^"]+;text-align:right;font-weight:bold;/', $html_slice_tmp)) ? 0 : $winner;
        $winner = (preg_match('/style="width:[^"]+;font-weight:bold/', $html_slice_tmp)) ? 1 : $winner;

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
          $score1 = ($winner == 0) ? 1 : 0;
          $score2 = ($winner == 1) ? 1 : 0;
        }

        if ($group_finished && strlen($winner) == 0) $group_finished = false;

        $match = array(
          'player1' => $id1,
          'player2' => $id2,
          'score1' => $score1,
          'score2' => $score2,
          'winner' => $winner
        );
        // print_r($match);die;

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

  // print_r($groups);die;
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


function round_name($round)
{
  switch ($round) {
    case 2:
      return FINAL_MATCH;
    case 4:
      return SEMIFINAL_MATCH;
    case 'bronze':
      return BRONZE_MATCH;
  }
  return "RO" . $round;
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
