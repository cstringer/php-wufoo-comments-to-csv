<?php
/***
 * php-wufoo-comments-to-csv.php - Create CSV of Wufoo form comments
 * $ rev 1.0.6, 2013-09-14 11:16, cstringer42@gmail.com $
 */
define ("PAGE_SIZE", 100);
define ("URL_BASE",  'https://[YOURSITE].wufoo.com/api/v3/forms');
define ("FORM_HASH", '[YOURFORMID]');
define ("USERPWD",   '[YOURPASSWORD]');
define ("USERAGENT", 'apps.chrisstringer.us - Wufoo Form Comment Extractor v1.0');
define ("FNAME_BASE", 'Form-Comments');
define ("FNAME_EXT", '.csv');

date_default_timezone_set ('America/Chicago');

/* init cURL lib */
$curl = curl_init();
curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($curl, CURLOPT_USERPWD, USERPWD);
curl_setopt ($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt ($curl, CURLOPT_USERAGENT, USERAGENT);

/* Get total number of comments */
$comment_count = 0;
// fetch comment count JSON feed
$cc_url = URL_BASE . '/' . FORM_HASH . '/comments/count.json';
curl_setopt ($curl, CURLOPT_URL, $cc_url);
$cc_rj = curl_exec ($curl);
$res_stat = curl_getinfo ($curl);
if ($res_stat['http_code'] != 200)
  {
  echo "ERROR: Code " . $res_stat['http_code'] . " fetching comment count.\n";
  exit();
  }
// decode JSON to PHP obj
$ccrj_data = json_decode ($cc_rj);
// extraxt comment count
$comment_count = $ccrj_data->{'Count'};
if ($comment_count == 0)
  {
  echo "ERROR: No comments attached to form " . FORM_HASH . "\n";
  exit();
  }
echo "Total comments to process: $comment_count\n";

/* Get comment data as JSON string
   NOTE: Since comments are returned in a limited page size,
   this steps through by PAGE_SIZE entries, creating one
   string of the JSON data as a single ordered array ($res_json)
*/
$res_json = "";
for ($page = 0; $page <= $comment_count; $page += PAGE_SIZE)
  {
  // build the query string, including page size and starting page number
  $qstr = '?pageSize=' . PAGE_SIZE . '&pageStart=' . $page;

  // build URL to REST endpoint
  $pg_url = URL_BASE . '/' . FORM_HASH .'/comments.json' . $qstr;
  curl_setopt ($curl, CURLOPT_URL, $pg_url);

  // execute request
  $rj = curl_exec ($curl);
  $res_stat = curl_getinfo ($curl);
  if ($res_stat['http_code'] != 200)
    {
    echo "ERROR: Code " . $res_stat['http_code'] . " fetching comment page $page.\n";
    exit();
    }

  if (strlen ($rj) > 0)
    {
    // strip object asignment, begin conversion to ordered array
    $rj = str_replace ('{"Comments":[', '', $rj);
    $rj = rtrim ($rj, '}');
    $rj = rtrim ($rj, ']');
    $rj .= ",";
    $res_json .= $rj;
    }
  }
// complete ordered array conversion
$res_json = rtrim ($res_json, ',');
$res_json = '[' . $res_json . ']';

// decode JSON to PHP obj
$res_data = json_decode ($res_json);
if (count ($res_data) != $comment_count)
  {
  echo "Warning: could not decode all comments!\n";
  }
echo "Decoded " . count ($res_data) . " of " . $comment_count . " comments.\n";

// create CSV file
$fname = FNAME_BASE . '_' . date ("Y-m-d") . FNAME_EXT;
$fh = fopen ($fname, 'w');
if ($fh == FALSE)
  {
  echo "ERROR: Can't create CSV file '" . $fname . "'\n";
  exit();
  }

// write comment data to file
for ($i = 0; $i < count ($res_data); $i++)
  {
  fputcsv ($fh, array (
    $res_data[$i]->{'CommentId'},
    $res_data[$i]->{'EntryId'},
    $res_data[$i]->{'DateCreated'},
    $res_data[$i]->{'CommentedBy'},
    $res_data[$i]->{'Text'}
    ));
  }

// close file
fclose ($fh);

// report success
echo "Created file: '$fname'\n\n";
exit();

?>
