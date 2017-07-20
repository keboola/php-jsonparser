<?php
namespace Keboola\Json;

use Keboola\Json\Test\ParserTestCase;
use Psr\Log\NullLogger;

class Header2Test extends ParserTestCase
{
    public function testAnalyze()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "first_third_fourth": "origin",
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }'
        );
        $parser->process($testFile->components);
        $h = new Header2([], $parser->getStruct());
        $h->processHeaders('root');
    }
}
