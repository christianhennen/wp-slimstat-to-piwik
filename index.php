<?php

// Switch displaying errors on or off
define('SHOW_ERRORS', true);

@ini_set('display_errors', (SHOW_ERRORS) ? 'On' : 'Off');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
if (defined('E_DEPRECATED')) {
    error_reporting(error_reporting() & ~E_DEPRECATED);
}

// Piwik configuration - Enter your Piwik installation URL, Site ID and user token here
const PIWIK_URL = "http://piwik.example.com";
const TOKEN = '12345678901234567890123456789012';
const SITE_ID = 1;

// Slimstat configuration - Enter your database credentials and installation URL here
const WEBSITE_DOMAIN = 'http://example.com';
const DB_HOST = 'localhost';
const DB_USER = 'user';
const DB_PASSWORD = 'password';
const DB_NAME = 'wordpress';
const TABLE_PREFIX = 'wp_';
const OLD_VERSION = false;


if (OLD_VERSION) {
    $slimstat_tables = array('slim_stats', 'slim_stats_archive');
} else {
    $slimstat_tables = array('slim_stats', 'slim_stats_3', 'slim_stats_archive', 'slim_stats_archive_3');
}
const DB_OPTION_FIELD = 'slimstat_to_piwik_db_upgrade';

// Helper functions
function debug($q, $c)
{
    return die("Query: " . $q . " <br /> Error: " . mysqli_error($c));
}

function in($needle, $haystack)
{
    return (false !== stristr($haystack, $needle)) ? true : false;
}

// Connect to database
$connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if (!$connection) {
    die("Could not connect to DB");
}

$firstrun_query_string = "SELECT option_name, option_value FROM " . TABLE_PREFIX . "options WHERE option_name = '" . DB_OPTION_FIELD . "'";
$firstrun_query = mysqli_query($connection, $firstrun_query_string) or debug($firstrun_query_string, $connection);
if (mysqli_num_rows($firstrun_query) === 0) {

    echo 'Updating database...';

    // create a new user string
    function s($length = 16)
    {
        $characters = "0123456789ABCDEF";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= $characters[rand(0, strlen($characters) - 1)];
        }
        if (!$connection) {
            return $str;
        }
        $s_query_string = "SELECT meta_value FROM " . TABLE_PREFIX . "usermeta WHERE meta_key = 'piwik_user_id' AND meta_value = '" . $str . "'";
        $s_query = mysqli_query($connection, $s_query_string) or debug($s_query_string, $connection);
        if (mysqli_num_rows($s_query) === 0) {
            return $str;
        } else {
            return s($length);
        }
    }

    // select the users
    $user_query_string = "SELECT * FROM " . TABLE_PREFIX . "users ORDER BY ID ASC";
    $user_query = mysqli_query($connection, $user_query_string) or debug($user_query_string, $connection);
    $id = 0;

    // for each, update with a piwik user id in the meta table
    while ($row = mysqli_fetch_array($user_query)) {
        $id = $row["ID"];
        $meta_query_string = "SELECT meta_value FROM " . TABLE_PREFIX . "usermeta WHERE meta_key = 'piwik_user_id' AND user_id = " . $id;
        $meta_query = mysqli_query($connection, $meta_query_string) or debug($meta_query_string, $connection);
        if (mysqli_num_rows($meta_query) === 0) {
            $piwik_user_id = s();
            $pui_query_string = "INSERT INTO " . TABLE_PREFIX . "usermeta (user_id, meta_key, meta_value) VALUES (" . $id . ", 'piwik_user_id', '" . $piwik_user_id . "')";
            $pui_query = mysqli_query($connection, $pui_query_string) or debug($pui_query_string, $connection);
        }
    }
    // alter the slim stat tables to set a processed marker
    foreach ($slimstat_tables as $slimstat_table) {
        $qs = "ALTER TABLE " . TABLE_PREFIX . $slimstat_table . " ADD COLUMN processed INT(1) NULL DEFAULT 0  AFTER dt";
        $q = mysqli_query($connection, $qs) or debug($qs, $connection);
    }
    $set_firstrun_query_string = "INSERT INTO " . TABLE_PREFIX . "options (option_name, option_value) VALUES ('" . DB_OPTION_FIELD . "', 0)";
    $set_firstrun_query = mysqli_query($connection, $set_firstrun_query_string) or debug($set_firstrun_query_string, $connection);
} else {
    echo "Database already up to date<br/>";
}
require_once "PiwikTracker.php";

echo "Processing records...<br/>";

// Get all the piwik user ids
$meta_query_string = "SELECT * FROM " . TABLE_PREFIX . "usermeta WHERE meta_key = 'piwik_user_id' ORDER BY umeta_id ASC";
$meta_query = mysqli_query($connection, $meta_query_string) or debug($meta_query_string, $connection);
$user_info = array();
while ($meta_row = mysqli_fetch_assoc($meta_query)) {
    $user_info[$meta_row["user_id"]] = $meta_row["meta_value"];
}

$option_query_string = "SELECT option_value FROM " . TABLE_PREFIX . "options WHERE option_name = '" . DB_OPTION_FIELD . "' LIMIT 1";
$option_query = mysqli_query($connection, $option_query_string) or debug($option_query_string, $connection);
$option = mysqli_fetch_assoc($option_query);
$current_table_id = (int)$option['option_value'];
$current_table = $slimstat_tables[$current_table_id];
echo 'Table: ' . $current_table . '<br/>';

// Get a selection of slim stat records
$tl_qs = "SELECT id FROM " . TABLE_PREFIX . $current_table . " WHERE processed = 0 ORDER BY id ASC";
$tl_q = mysqli_query($connection, $tl_qs) or debug($tl_qs, $connection);
$total_left = mysqli_num_rows($tl_q);
echo $total_left . " records left<br/>";

if ($total_left == 0) {
    if ($current_table_id < count($slimstat_tables) - 1) {
        $current_table_id++;
        $qs = "UPDATE " . TABLE_PREFIX . "options SET option_value = " . $current_table_id . " WHERE option_name = '" . DB_OPTION_FIELD . "'";
        $q = mysqli_query($connection, $qs) or debug($qs, $connection);
        echo "<meta http-equiv=\"refresh\" content=\"1\">";
    } else {
        echo 'All records processed';
    }
} else {
    if (OLD_VERSION OR ($current_table != 'slim_stats' AND $current_table != 'slim_stats_archive')) {
        // Get all resolutions
        $res_query_string = "SELECT * FROM " . TABLE_PREFIX . "slim_screenres ORDER BY screenres_id";
        $res_query = mysqli_query($connection, $res_query_string) or debug($res_query_string, $c);
        $screenres = array();
        while ($res_row = mysqli_fetch_assoc($res_query)) {
            $screenres[$res_row["screenres_id"]] = $res_row["resolution"];
        }

        // Get all user agents
        $browser_query_string = "SELECT * FROM " . TABLE_PREFIX . "slim_browsers ORDER BY browser_id";
        $browser_query = mysqli_query($connection, $browser_query_string) or debug($browser_query_string, $c);
        $user_agents = array();
        $browsers = array();
        while ($browser_row = mysqli_fetch_assoc($browser_query)) {
            $user_agent = "";

            $browser = strtolower($browser_row["browser"]);
            $platform = $browser_row["platform"];
            $version = $browser_row["version"];

            if (in("win2000", $platform)) {
                $user_agent .= " Windows NT 5.0";
            } else if (in("winxp", $platform)) {
                $user_agent .= " Windows NT 5.1";
            } else if (in("winvista", $platform)) {
                $user_agent .= " Windows NT 6.0";
            } else if (in("win7", $platform)) {
                $user_agent .= " Windows NT 6.1";
            } else if (in("win8", $platform)) {
                $user_agent .= " Windows NT 6.2";
            } else if (in("macosx", $platform)) {
                $user_agent .= " Mac OS X";
            } else if (in("ios", $platform)) {
                $user_agent .= " iPhone";
            } else {
                $user_agent .= " " . $platform;
            }

            if (in("ie", $browser)) {
                $user_agent .= " MSIE " . $version . ".0";
            } else if (in("firefox", $browser)) {
                $user_agent .= " Firefox/" . $version . ".0";
            } else {
                $user_agent .= " " . $browser . " " . $version . ".0";
            }

            $user_agents[$browser_row["browser_id"]] = $user_agent;

            $browsers[$browser_row["browser_id"]] = array(
                array("browser", $browser),
                array("version", $version),
                array("platform", $platform),
                array("mobile", ($browser_row["type"] == "2") ? TRUE : FALSE),
            );
        }
    }

    $ss_qs = "SELECT id FROM " . TABLE_PREFIX . $current_table . " WHERE processed = 0 ORDER BY id ASC LIMIT 0, 50";
    $ss_q = mysqli_query($connection, $ss_qs) or debug($ss_qs, $connection);
    $total_to_load = mysqli_num_rows($ss_q);
    $process_ids = array("" => "");
    if ($total_to_load > 0) {
        while ($row = mysqli_fetch_assoc($ss_q)) {
            $process_ids[$row["id"]] = $row["id"];
        }
        $process_ids_str = join(",", $process_ids);
        $process_ids_str = substr($process_ids_str, 1, strlen($process_ids_str));

        $ss_qs = "SELECT * FROM " . TABLE_PREFIX . $current_table . " WHERE id IN (" . $process_ids_str . ")";
        $ss_q = mysqli_query($connection, $ss_qs) or debug($ss_qs, $connection);

        // Loop through the selection of slim stats...
        while ($row = mysqli_fetch_assoc($ss_q)) {
            $id = $row["id"];
            // Check if the user exists, if they don't they're anon
            if ($row["user"] == "") {
                $user = "0";
            } else if (isset($user_info[$row["user"]])) {
                $user = $user_info[$row["user"]];
            } else {
                $user = "0";
            }
            if (OLD_VERSION OR ($current_table != 'slim_stats' AND $current_table != 'slim_stats_archive')) {
                $content_info_id = $row["content_info_id"];
                $content_query_string = "SELECT " . TABLE_PREFIX . "posts.post_title FROM " . TABLE_PREFIX . "slim_content_info " .
                    "INNER JOIN " . TABLE_PREFIX . "posts ON " . TABLE_PREFIX . "posts.ID = " . TABLE_PREFIX . "slim_content_info.content_id " .
                    "WHERE " . TABLE_PREFIX . "slim_content_info.content_info_id = " . $content_info_id . " AND " . TABLE_PREFIX . "slim_content_info.content_id != 0 ";
            } else {
                $content_info_id = $row["content_id"];
                $content_query_string = "SELECT " . TABLE_PREFIX . "posts.post_title FROM " . TABLE_PREFIX . $current_table .
                    " INNER JOIN " . TABLE_PREFIX . "posts ON " . TABLE_PREFIX . "posts.ID = " . TABLE_PREFIX . $current_table . ".content_id " .
                    "WHERE " . TABLE_PREFIX . $current_table . ".content_id = " . $content_info_id . " AND " . TABLE_PREFIX . $current_table . ".content_id != 0 ";
            }

            $content_query = mysqli_query($connection, $content_query_string) or debug($content_query_string, $connection);
            $title = "";
            if (mysqli_num_rows($content_query) > 0) {
                $title = mysqli_fetch_row($content_query);
                $title = $title[0];
            }

            if (OLD_VERSION OR ($current_table != 'slim_stats' AND $current_table != 'slim_stats_archive')) {
                $domain = (strlen($row["domain"] > 0)) ? "http://" . $row["domain"] : WEBSITE_DOMAIN;
                $res = $screenres[$row["screenres_id"]];
                $ip = long2ip($row["ip"]);
                $user_agent = $user_agents[$row["browser_id"]];
                $custom = $browsers[$row["browser_id"]];
            } else {
                $domain = WEBSITE_DOMAIN;
                $res = $row["resolution"];
                $ip = $row["ip"];
                $user_agent = $row["user_agent"];
            }
            if ($current_table == 'slim_stats_archive') {
                $ip = long2ip($row["ip"]);
            }
            $referer = $row["referer"];
            $webpage = $domain . $row["resource"];
            $reso = explode("x", $res);
            $dt = $row["dt"];
            $country = $row["country"];
            // if user can't be found, say they're anon
            if ($user == "0") {
                $custom[] = array("anonymous", "Anonymous");
            }
            $lang = $row["language"];
            $datetime = date('Y-m-d H:i:s', $dt);

            // Start the tracker
            $t = new PiwikTracker(SITE_ID, PIWIK_URL . '/');

            $t->setIdSite(SITE_ID);
            $t->setTokenAuth(TOKEN);

            $t->setBrowserLanguage($lang);
            $t->setIp($ip);
            $t->setResolution($reso[0], $reso[1]);
            $t->setBrowserHasCookies(true);

            // Set the plugins
            $plugins = $row['plugins'];
            $flash = in("flash", $plugins);
            $java = in("java", $plugins);
            $director = in("director", $plugins);
            $quickTime = in("quicktime", $plugins);
            $realPlayer = in("real", $plugins);
            $pdf = in("acrobat", $plugins);
            $windowsMedia = in("mediaplayer", $plugins);
            $gears = in("gears", $plugins);
            $silverlight = in("silverlight", $plugins);
            $t->setPlugins($flash, $java, $director, $quickTime, $realPlayer, $pdf, $windowsMedia, $gears, $silverlight);

            // Custom vars
            for ($i = 0; $i < count($custom); $i++) {
                $t->setCustomVariable($i + 1, $custom[$i][0], $custom[$i][1]);
            }

            $t->setForceVisitDateTime($dt);
            $t->setUrlReferrer($referer);
            $t->setUserAgent($user_agent);

            // If you wanted to force to record the page view or conversion to a specific visitorId
            if ($user != "0") {
                $t->setVisitorId($user);
            }

            // Mandatory: set the URL being tracked
            $t->setUrl($webpage);

            // Finally, track the page view with a Custom Page Title
            // In the standard JS API, the content of the <title> tag would be set as the page title
            $t->doTrackPageView($title);
        }
        $update_qs = "UPDATE " . TABLE_PREFIX . $current_table . " SET processed = 1 WHERE id IN (" . $process_ids_str . ")";
        $update_q = mysqli_query($connection, $update_qs) or debug($update_qs, $connection);

        echo "<meta http-equiv=\"refresh\" content=\"1\">";
    }
}
mysqli_close($connection);