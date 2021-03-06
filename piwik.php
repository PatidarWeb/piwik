<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @package Piwik
 */

use Piwik\Tracker\RequestSet;
use Piwik\Tracker;
use Piwik\Tracker\Handler;

// Note: if you wish to debug the Tracking API please see this documentation:
// http://developer.piwik.org/api-reference/tracking-api#debugging-the-tracker

if (!defined('PIWIK_DOCUMENT_ROOT')) {
    define('PIWIK_DOCUMENT_ROOT', dirname(__FILE__) == '/' ? '' : dirname(__FILE__));
}

if (file_exists(PIWIK_DOCUMENT_ROOT . '/bootstrap.php')) {
    require_once PIWIK_DOCUMENT_ROOT . '/bootstrap.php';
}

error_reporting(E_ALL | E_NOTICE);
@ini_set('xdebug.show_exception_trace', 0);
@ini_set('magic_quotes_runtime', 0);

if (!defined('PIWIK_USER_PATH')) {
    define('PIWIK_USER_PATH', PIWIK_DOCUMENT_ROOT);
}
if (!defined('PIWIK_INCLUDE_PATH')) {
    define('PIWIK_INCLUDE_PATH', PIWIK_DOCUMENT_ROOT);
}

@ignore_user_abort(true);

if (file_exists(PIWIK_INCLUDE_PATH . '/vendor/autoload.php')) {
    $vendorDirectory = PIWIK_INCLUDE_PATH . '/vendor';
} else {
    $vendorDirectory = PIWIK_INCLUDE_PATH . '/../..';
}
require_once $vendorDirectory . '/autoload.php';

require_once PIWIK_INCLUDE_PATH . '/core/Plugin/Controller.php';
require_once PIWIK_INCLUDE_PATH . '/core/Plugin/ControllerAdmin.php';

\Piwik\Plugin\ControllerAdmin::disableEacceleratorIfEnabled();

require_once PIWIK_INCLUDE_PATH . '/libs/upgradephp/upgrade.php';
require_once PIWIK_INCLUDE_PATH . '/core/testMinimumPhpVersion.php';
require_once PIWIK_INCLUDE_PATH . '/core/Singleton.php';
require_once PIWIK_INCLUDE_PATH . '/core/Plugin/Manager.php';
require_once PIWIK_INCLUDE_PATH . '/core/Plugin.php';
require_once PIWIK_INCLUDE_PATH . '/core/Common.php';
require_once PIWIK_INCLUDE_PATH . '/core/Piwik.php';
require_once PIWIK_INCLUDE_PATH . '/core/IP.php';
require_once PIWIK_INCLUDE_PATH . '/core/UrlHelper.php';
require_once PIWIK_INCLUDE_PATH . '/core/Url.php';
require_once PIWIK_INCLUDE_PATH . '/core/SettingsPiwik.php';
require_once PIWIK_INCLUDE_PATH . '/core/SettingsServer.php';
require_once PIWIK_INCLUDE_PATH . '/core/Tracker.php';
require_once PIWIK_INCLUDE_PATH . '/core/Config.php';
require_once PIWIK_INCLUDE_PATH . '/core/Translate.php';
require_once PIWIK_INCLUDE_PATH . '/core/Tracker/Cache.php';
require_once PIWIK_INCLUDE_PATH . '/core/Tracker/Request.php';
require_once PIWIK_INCLUDE_PATH . '/core/Cookie.php';

Tracker::loadTrackerEnvironment();

session_cache_limiter('nocache');
@date_default_timezone_set('UTC');

$tracker    = new Tracker();
$requestSet = new RequestSet();

ob_start();

try {
    $handler  = Handler\Factory::make();
    $response = $tracker->main($handler, $requestSet);

    if (!is_null($response)) {
        echo $response;
    }

} catch (Exception $e) {
    echo "Error:" . $e->getMessage();
    exit(1);
}

if (ob_get_level() > 1) {
    ob_end_flush();
}