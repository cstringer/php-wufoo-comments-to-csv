<?php
define ("PAGE_SIZE", 100);
define ("URL_BASE",  'https://[YOURSITE].wufoo.com/api/v3/forms');
define ("FORM_HASH", '[YOURFORMID]');
define ("USERPWD",   '[YOURPASSWORD]');
define ("USERAGENT", 'apps.chrisstringer.us - Wufoo Form Comment Extractor v1.0');
define ("FNAME_BASE", 'Form-Comments');
define ("FNAME_EXT", '.csv');

date_default_timezone_set ('America/Chicago');

// init cURL lib
$curl = curl_init();
curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($curl, CURLOPT_USERPWD, USERPWD);
curl_setopt ($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt ($curl, CURLOPT_USERAGENT, USERAGENT);

// get total number of comments
$comment_count = 0;
curl_setopt ($curl, CURLOPT_URL, URL_BASE . '/' . FORM_HASH . '/comments/count.json');
$cc_rj = curl_exec ($curl);
$res_stat = curl_getinfo ($curl);
if ($res_stat['http_code'] != 200)
  {
  exit();
  }
$ccrj_data = json_decode ($cc_rj);
$comment_count = $ccrj_data->{'Count'};
if ($comment_count == 0)
  {
  exit();
  }
echo "Total comments to process: $comment_count\n";

// get comment data as JSON string
$res_json = "";
for ($page = 0; $page <= $comment_count; $page += PAGE_SIZE)
  {
  $qstr = '?pageSize=' . PAGE_SIZE . '&pageStart=' . $page;

  $pg_url = URL_BASE . '/' . FORM_HASH .'/comments.json' . $qstr;
  curl_setopt ($curl, CURLOPT_URL, $pg_url);

  $rj = curl_exec ($curl);
  $res_stat = curl_getinfo ($curl);
  if ($res_stat['http_code'] != 200)
    {
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

// save results
$fname = FNAME_BASE . '_' . date ("Y-m-d") . FNAME_EXT;
$fh = fopen ($fname, 'w');
if ($fh == FALSE)
  {
  echo "ERROR: Can't create CSV file '" . $fname . "'\n";
  exit();
  }

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
fclose ($fh);

echo "Created file: '$fname'\n\n";

exit();

?>
