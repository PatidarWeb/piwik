<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Tests\System;

use Piwik\Date;
use Piwik\Plugins\SitesManager\API;
use Piwik\Tests\Framework\TestCase\SystemTestCase;
use Piwik\Tests\Fixtures\ManySitesImportedLogs;
use Piwik\Tests\Framework\Fixture;
use Exception;

/**
 * Tests to call the cron core:archive command script and check there is no error,
 * Then call the API testing for "Browser archiving is disabled" (see disableArchiving)
 * This tests that, when archiving is disabled,
 *  then Piwik API will return data that was pre-processed during archive.php run
 *
 * @group Core
 * @group ArchiveCronTest
 */
class ArchiveCronTest extends SystemTestCase
{
    /** @var ManySitesImportedLogs */
    public static $fixture = null; // initialized below class definition

    public function getApiForTesting()
    {
        $results = array();

        // First, API calls for Segmented reports
        // Disabling these tests as they randomly fail... This could actually be a bug.
        // FIXME - I have failed finding the cause for these test to randomly fail
        // eg.
        foreach (self::$fixture->getDefaultSegments() as $segmentName => $info) {
            $results[] = array('VisitsSummary.get', array('idSite'     => 'all',
                                                          'date'       => '2012-08-09',
                                                          'periods'    => array('day', 'week', 'month', 'year'),
                                                          'segment'    => $info['definition'],
                                                          'testSuffix' => '_' . $segmentName));
        }

        // API Call Without segments
        $results[] = array('VisitsSummary.get', array('idSite'  => 'all',
                                                      'date'    => '2012-08-09',
                                                      'periods' => array('day', 'month', 'year',  'week')));

        $results[] = array('VisitsSummary.get', array('idSite'     => 'all',
                                                      'date'       => '2012-08-09',
                                                      'periods'    => array('day', 'week', 'month', 'year'),
                                                      'segment'    => 'browserCode==EP',
                                                      'testSuffix' => '_nonPreArchivedSegment'));

        $segments = array(ManySitesImportedLogs::SEGMENT_PRE_ARCHIVED,
                          ManySitesImportedLogs::SEGMENT_PRE_ARCHIVED_CONTAINS_ENCODED
        );
        foreach($segments as $idx => $segment) {
            // Test with a pre-processed segment
            // TODO: 'VisitFrequency.get' fails because it modifies the segment; i guess cron archiving doesn't
            //       invoke VisitFrequency archiving...
            $results[] = array(array('VisitsSummary.get', 'Live.getLastVisitsDetails'),
                               array('idSite'     => '1',
                                     'date'       => '2012-08-09',
                                     'periods'    => array('day', 'year'),
                                     'segment'    => $segment,
                                     'testSuffix' => '_preArchivedSegment_' . $idx));
        }

        return $results;
    }

    public function testArchivePhpCron()
    {
        if(self::isPhpVersion53()) {
            $this->markTestSkipped('Fails on PHP 5.3 once in a blue moon.');
        }

        $this->setLastRunArchiveOptions();
        $output = $this->runArchivePhpCron();

        $this->compareArchivePhpOutputAgainstExpected($output);

        foreach ($this->getApiForTesting() as $testInfo) {

            list($api, $params) = $testInfo;

            if (!isset($params['testSuffix'])) {
                $params['testSuffix'] = '';
            }
            $params['testSuffix'] .= '_noOptions';
            $params['disableArchiving'] = true;

            $success = $this->runApiTests($api, $params);

            if (!$success) {
                var_dump($output);
            }
        }
    }

    private function setLastRunArchiveOptions()
    {
        $periodTypes = array('day', 'periods');
        $idSites = API::getInstance()->getAllSitesId();

        $daysAgoArchiveRanSuccessfully = 1500;
        $this->assertTrue($daysAgoArchiveRanSuccessfully > (\Piwik\CronArchive::ARCHIVE_SITES_WITH_TRAFFIC_SINCE / 86400));
        $time = Date::factory(self::$fixture->dateTime)->subDay($daysAgoArchiveRanSuccessfully)->getTimestamp();

        foreach ($periodTypes as $period) {
            foreach ($idSites as $idSite) {
                // lastRunKey() function inlined
                $lastRunArchiveOption = "lastRunArchive" . $period . "_" . $idSite;
                \Piwik\Option::set($lastRunArchiveOption, $time);
            }
        }
    }

    private function runArchivePhpCron()
    {
        $archivePhpScript = PIWIK_INCLUDE_PATH . '/tests/PHPUnit/proxy/archive.php';
        $urlToProxy = Fixture::getRootUrl() . 'tests/PHPUnit/proxy/index.php';

        // create the command
        $cmd = "php \"$archivePhpScript\" --url=\"$urlToProxy\" 2>&1";

        // run the command
        exec($cmd, $output, $result);
        if ($result !== 0 || stripos($result, "error")) {
            $message = 'This failed once after a lunar eclipse, and it has again randomly failed.';
            $message .= "\n\narchive cron failed: " . implode("\n", $output) . "\n\ncommand used: $cmd";
            $this->markTestSkipped($message);
        }

        return $output;
    }

    private function compareArchivePhpOutputAgainstExpected($output)
    {
        $output = implode("\n", $output);

        $fileName = 'test_ArchiveCronTest_archive_php_cron_output.txt';
        list($pathProcessed, $pathExpected) = static::getProcessedAndExpectedDirs();

        $expectedOutputFile = $pathExpected . $fileName;
        $processedFile = $pathProcessed . $fileName;

        file_put_contents($processedFile, $output);

        try {
            $this->assertTrue(is_readable($expectedOutputFile));
            $this->assertEquals(file_get_contents($expectedOutputFile), $output);
        } catch (Exception $ex) {
            $this->comparisonFailures[] = $ex;
        }
    }
}

ArchiveCronTest::$fixture = new ManySitesImportedLogs();
ArchiveCronTest::$fixture->addSegments = true;