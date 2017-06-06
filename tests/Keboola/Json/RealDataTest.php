<?php
namespace Keboola\Json;

use Keboola\Json\Test\ParserTestCase;

class RealDataTest extends ParserTestCase
{
    public function testProcess()
    {
        $parser = $this->getParser();

        $testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

        $data = $this->loadJson('Json_tweets_pinkbike');

        $parser->process($data);

        foreach ($parser->getCsvFiles() as $name => $table) {
            // compare result files
            self::assertEquals(
                file_get_contents("{$testFilesPath}/{$name}.csv"),
                file_get_contents($table->getPathname())
            );

            // compare column counts
            $parsedFile = file($table->getPathname());
            foreach ($parsedFile as $row) {
                if (empty($headerCount)) {
                    $headerCount = count($row);
                } else {
                    self::assertEquals($headerCount, count($row));
                }
            }
        }

        // make sure all the files are present
        $dir = scandir($testFilesPath);
        array_walk($dir, function (&$val) {
            $val = str_replace(".csv", "", $val);
        });
        self::assertEquals(array(".",".."), array_diff($dir, array_keys($parser->getCsvFiles())));
        self::assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
    }

    public function testTypeCharacters()
    {
        $parser = $this->getParser();

        $data = $this->loadJson('Json_tweets_pinkbike');

        $parser->process($data, 'a/b.c&d@e$f');

        self::assertEquals(
            [
                'a_b_c_d_e_f',
                'a_b_c_d_e_f_statuses',
                'a_b_c_d_e_f_statuses_user_entities_url_urls',
                'a_b_c_d_e_f_statuses_user_entities_url_urls_indices',
                'a_b_c_d_e_f_statuses_entities_hashtags',
                'a_b_c_d_e_f_statuses_entities_hashtags_indices',
                'a_b_c_d_e_f_statuses_entities_urls',
                'a_b_c_d_e_f_statuses_entities_urls_indices',
                'a_b_c_d_e_f_statuses_entities_user_mentions',
                'a_b_c_d_e_f_statuses_entities_user_mentions_indices',
                'a_b_c_d_e_f_statuses_retweeted_status_user_entities_url_urls',
                'abcdefsrueuui__status_user_entities_url_urls_indices',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_urls',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_urls_indices',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_user_mentions',
                'abcdefsreui__status_entities_user_mentions_indices',
                'a_b_c_d_e_f_statuses_user_entities_description_urls',
                'a_b_c_d_e_f_statuses_user_entities_description_urls_indices',
                'a_b_c_d_e_f_statuses_entities_media',
                'a_b_c_d_e_f_statuses_entities_media_indices',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_media',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_media_indices',
            ],
            array_keys($parser->getCsvFiles())
        );
    }

    public function testRowCount()
    {
        $parser = $this->getParser();

        $data = $this->loadJson('Json_tweets_pinkbike');

        $parser->process($data);

        // -1 offset to compensate for header
        $rows = -1;
        $handle = fopen($parser->getCsvFiles()['root_statuses'], 'r');
        while (fgetcsv($handle)) {
            $rows++;
        }
        self::assertEquals(count($data[0]->statuses), $rows);
    }

    public function testValidateHeader()
    {
        $parser = $this->getParser();

        $header = array(
            "KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Click-through Conversions",
            "KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: View-through Conversions",
            "KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Total Conversions",
            "KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Click-through Revenue",
            "KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: View-through Revenue",
            "KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Total Revenue",
            "KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Click-through Conversions",
            "KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: View-through Conversions",
            "KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Total Conversions",
            "KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Click-through Revenue",
            "KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: View-through Revenue",
            "KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Total Revenue",
            "KIND_Projects Retargeting : KINDProjects_Retargeting: Click-through Conversions",
            "KIND_Projects Retargeting : KINDProjects_Retargeting: View-through Conversions",
            "KIND_Projects Retargeting : KINDProjects_Retargeting: Total Conversions",
            "KIND_Projects Retargeting : KINDProjects_Retargeting: Click-through Revenue",
            "KIND_Projects Retargeting : KINDProjects_Retargeting: View-through Revenue",
            "KIND_Projects Retargeting : KIND_Projects_Retargeting: Total Revenue",
            "KIND_Conversions : KIND_Projects_Conversions_Votes: Click-through Conversions",
            "KIND_Conversions : KIND_Projects_Conversions_Votes: View-through Conversions",
            "KIND_Conversions : KIND_Projects_Conversions_Votes: Total Conversions",
            "KIND_Conversions : KIND_Projects_Conversions_Votes: Click-through Revenue",
            "KIND_Conversions : KIND_Projects_Conversions_Votes: View-through Revenue",
            "KIND_Conversions : KIND_Projects_Conversions_Votes: Total Revenue",
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Click-through Conversions",
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: View-through Conversions",
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Total Conversions",
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Click-through Revenue",
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: View-through Revenue",
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Total Revenue");

        $validHeader = self::callMethod($parser, 'validateHeader', array($header));

        $expectedHeader = array(
            "KSKSCtC__SEM_Conversions__Click-through_Conversions",
            "KSKSVtC__KIND_Baseline_SEM_Conversions__View-through_Conversions",
            "KSKSTC____KIND_Baseline_SEM_Conversions__Total_Conversions",
            "KSKSCtR____KIND_Baseline_SEM_Conversions__Click-through_Revenue",
            "KSKSVtR____KIND_Baseline_SEM_Conversions__View-through_Revenue",
            "KSKSTR____KIND_Baseline_SEM_Conversions__Total_Revenue",
            "KKCtC__Click-through_Conversions",
            "KKVtC__KIND_Strong_Conversions_Pledges__View-through_Conversions",
            "KKTC____KIND_Strong_Conversions_Pledges__Total_Conversions",
            "KKCtR____KIND_Strong_Conversions_Pledges__Click-through_Revenue",
            "KKVtR____KIND_Strong_Conversions_Pledges__View-through_Revenue",
            "KKTR____KIND_Strong_Conversions_Pledges__Total_Revenue",
            "KRKCtC____KINDProjects_Retargeting__Click-through_Conversions",
            "KRKVtC____KINDProjects_Retargeting__View-through_Conversions",
            "KRKTC__Retargeting___KINDProjects_Retargeting__Total_Conversions",
            "KRKCtR____KINDProjects_Retargeting__Click-through_Revenue",
            "KRKVtR____KINDProjects_Retargeting__View-through_Revenue",
            "KRKTR__Retargeting___KIND_Projects_Retargeting__Total_Revenue",
            "2282172e8d22d91520151a6df2413dd6",
            "KKVtC__KIND_Projects_Conversions_Votes__View-through_Conversions",
            "KKTC____KIND_Projects_Conversions_Votes__Total_Conversions",
            "KKCtR____KIND_Projects_Conversions_Votes__Click-through_Revenue",
            "KKVtR____KIND_Projects_Conversions_Votes__View-through_Revenue",
            "KKTR____KIND_Projects_Conversions_Votes__Total_Revenue",
            "08dcf2d087429e430b5b060f138472c6",
            "KKVtC__View-through_Conversions",
            "KKTC____KIND_Projects_Conversions_Submissions__Total_Conversions",
            "KKCtR__Click-through_Revenue",
            "KKVtR__View-through_Revenue",
            "KKTR____KIND_Projects_Conversions_Submissions__Total_Revenue"
        );

        self::assertEquals($expectedHeader, $validHeader);
    }

    public function testPrimaryKeys()
    {
        $parser = $this->getParser();

        $data = $this->loadJson('Json_tweets_pinkbike');

        $pks = [
            'root_statuses' => 'id',
            'root_statuses_entities_urls' => 'url,JSON_parentId'
        ];
        $parser->process($data);
        $parser->addPrimaryKeys($pks);

        $files = $parser->getCsvFiles();
        foreach ($pks as $table => $pk) {
            self::assertEquals($pk, $files[$table]->getPrimaryKey());
        }
        self::assertEquals(null, $files['root']->getPrimaryKey());
    }

    /**
     * Ensure "proper" JSON that doesn't require the upgrade is parsed the same as before
     */
    public function testProcessWithAutoUpgradeToArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);

        $testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

        $data = $this->loadJson('Json_tweets_pinkbike');

        $parser->process($data);

        foreach ($parser->getCsvFiles() as $name => $table) {
            // compare result files
            self::assertEquals(
                file_get_contents("{$testFilesPath}/{$name}.csv"),
                file_get_contents($table->getPathname())
            );

            // compare column counts
            $parsedFile = file($table->getPathname());
            foreach ($parsedFile as $row) {
                if (empty($headerCount)) {
                    $headerCount = count($row);
                } else {
                    self::assertEquals($headerCount, count($row));
                }
            }
        }

        // make sure all the files are present
        $dir = scandir($testFilesPath);
        array_walk($dir, function (&$val) {
            $val = str_replace(".csv", "", $val);
        });
        self::assertEquals(array(".",".."), array_diff($dir, array_keys($parser->getCsvFiles())));
        self::assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
    }

    public function testAssignLongColName()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);

        $testFile = $this->loadJson('kbc_components');

        $parser->process($testFile->components);

        $result = '"id","d__DistributionGroups_outputs_histogramEstimates_persistent",' .
            '"d__m__DistributionGroups_outputs_groupCharacteristics_persistent"' . PHP_EOL .
            '"ag-forecastio","",""' . PHP_EOL .
            '"rcp-distribution-groups","1","1"' . PHP_EOL;

        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }
}
