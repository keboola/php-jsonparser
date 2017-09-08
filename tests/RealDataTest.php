<?php

namespace Keboola\Json\Tests;

use Keboola\Json\Analyzer;
use Keboola\Json\Parser;
use Keboola\Json\Structure;
use Psr\Log\NullLogger;

class RealDataTest extends ParserTestCase
{
    public function testProcess()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        self::assertEquals([".",".."], array_diff($dir, array_keys($parser->getCsvFiles())));
        self::assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
    }

    public function testTypeCharacters()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
                'a_e_f_statuses_retweeted_status_user_entities_url_urls_indices',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_urls',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_urls_indices',
                'a_b_c_d_e_f_statuses_retweeted_status_entities_user_mentions',
                'a_e_f_statuses_retweeted_status_entities_user_mentions_indices',
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $header = [
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
            "KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Total Revenue"];
        $data = array_combine($header, array_fill(0, count($header), 'boo'));
        $parser->process(['items' => \Keboola\Utils\arrayToObject($data)], 'root');
        $file = $parser->getCsvFiles()['root'];

        $expectedHeader = [
            't_KIND_Baseline_SEM_Conversions_Click-through_Conversions',
            't_KIND_Baseline_SEM_Conversions_View-through_Conversions',
            'KIND_Baseline_SEM_Conversions_Total_Conversions',
            't_KIND_Baseline_SEM_Conversions_Click-through_Revenue',
            't_KIND_Baseline_SEM_Conversions_View-through_Revenue',
            'Conversions_KIND_Baseline_SEM_Conversions_Total_Revenue',
            't_KIND_Strong_Conversions_Pledges_Click-through_Conversions',
            't_KIND_Strong_Conversions_Pledges_View-through_Conversions',
            'Pledges_KIND_Strong_Conversions_Pledges_Total_Conversions',
            't_KIND_Strong_Conversions_Pledges_Click-through_Revenue',
            't_KIND_Strong_Conversions_Pledges_View-through_Revenue',
            'Pledges_KIND_Strong_Conversions_Pledges_Total_Revenue',
            't_KINDProjects_Retargeting_Click-through_Conversions',
            't_KINDProjects_Retargeting_View-through_Conversions',
            'Retargeting_KINDProjects_Retargeting_Total_Conversions',
            't_KINDProjects_Retargeting_Click-through_Revenue',
            't_Retargeting_KINDProjects_Retargeting_View-through_Revenue',
            'Retargeting_KIND_Projects_Retargeting_Total_Revenue',
            't_KIND_Projects_Conversions_Votes_Click-through_Conversions',
            't_KIND_Projects_Conversions_Votes_View-through_Conversions',
            'KIND_Projects_Conversions_Votes_Total_Conversions',
            't_KIND_Projects_Conversions_Votes_Click-through_Revenue',
            't_KIND_Projects_Conversions_Votes_View-through_Revenue',
            'Conversions_KIND_Projects_Conversions_Votes_Total_Revenue',
            't_Projects_Conversions_Submissions_Click-through_Conversions',
            't_Projects_Conversions_Submissions_View-through_Conversions',
            'KIND_Projects_Conversions_Submissions_Total_Conversions',
            't_KIND_Projects_Conversions_Submissions_Click-through_Revenue',
            't_KIND_Projects_Conversions_Submissions_View-through_Revenue',
            'KIND_Projects_Conversions_Submissions_Total_Revenue'
        ];

        self::assertEquals($expectedHeader, $file->getHeader());
    }

    public function testPrimaryKeys()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        self::assertEquals([".",".."], array_diff($dir, array_keys($parser->getCsvFiles())));
        self::assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
    }

    public function testAssignLongColName()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = $this->loadJson('kbc_components');
        $parser->process($testFile->components);
        $result = '"id","DistributionGroups_outputs_histogramEstimates_persistent",' .
            '"DistributionGroups_outputs_groupCharacteristics_persistent"' . "\n" .
            '"ag-forecastio","",""' . "\n" .
            '"rcp-distribution-groups","1","1"' . "\n";

        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testFloatHash()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "id": "a1",
                        "data": [
                            0.03
                        ]
                    },
                    {
                        "id": "b1",
                        "data": [
                            0.01
                        ]
                    }
                ]
            }'
        );
        $parser->process($testFile->components);
        self::assertEquals(['root', 'root_data'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"data\"\n" .
            "\"a1\",\"root_b986422c776b4dc62f413f7454753d94\"\n" .
            "\"b1\",\"root_692c6f543db16772f6a86c8baa0e62be\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"0.03\",\"root_b986422c776b4dc62f413f7454753d94\"\n" .
            "\"0.01\",\"root_692c6f543db16772f6a86c8baa0e62be\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_data']));
    }

    public function testEmptyArray()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "id": "a1",
                        "data": []
                    },
                    {
                        "id": "b1",
                        "data": ["test"]
                    }
                ]
            }'
        );
        $parser->process($testFile->components);
        self::assertEquals(['root', 'root_data'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"data\"\n" .
            "\"a1\",\"\"\n" .
            "\"b1\",\"root_5befb93e296c823d90e855bc25136d9e\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"test\",\"root_5befb93e296c823d90e855bc25136d9e\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_data']));
    }

    public function testEmptyObject()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '[
                {
                    "id": 1,
                    "longDescription": null,
                    "hasUI": false,
                    "data": {},
                    "flags": []
                }
            ]'
        );
        $parser->process($testFile);
        self::assertEquals(['root'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"longDescription\",\"hasUI\",\"flags\"\n" .
            "\"1\",\"\",\"\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testEmptyAndNonEmptyObject()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '[
                {
                    "id": 1,
                    "longDescription": null,
                    "hasUI": false,
                    "data": {},
                    "flags": []
                }
            ]'
        );
        $parser->process($testFile);
        $testFile = \Keboola\Utils\jsonDecode(
            '[
                {
                    "id": 2,
                    "longDescription": null,
                    "hasUI": false,
                    "data": {"a": "b"},
                    "flags": []
                }
            ]'
        );
        $parser->process($testFile);
        self::assertEquals(['root'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"longDescription\",\"hasUI\",\"data_a\",\"flags\"\n" .
            "\"1\",\"\",\"\",\"\",\"\"\n" .
            "\"2\",\"\",\"\",\"b\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testAlmostEmptyObject()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '[
                {
                    "id": 1,
                    "longDescription": null,
                    "hasUI": false,
                    "data": {
                        "a": {},
                        "b": null
                    },
                    "flags": []
                }
            ]'
        );
        $parser->process($testFile);
        self::assertEquals(['root'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"longDescription\",\"hasUI\",\"data_b\",\"flags\"\n" .
            "\"1\",\"\",\"\",\"\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }
}
