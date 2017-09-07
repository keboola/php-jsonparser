# JSON Parser

## Description
Parses JSON strings into CSV files. Creates multiple tables from a single JSON. 
Uses Keboola\CsvFile for results. The root of the JSON must be an array. JSON parser
is part of [Generic Extractor](https://github.com/keboola/generic-extractor/)
(see also end-user [documentation](https://developers.keboola.com/extend/generic-extractor/)).

## Usage

```php
use Keboola\Json\Parser;
$parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
$file = file_get_contents("some/data.json");
$json = json_decode($file);

$parser->process($json);

$results = $parser->getCsvFiles(); // array of CsvFile objects
```

# \Keboola\Json\Analyzer
Analyzes JSON data for JSON parser.

## __construct(\Psr\Log\LoggerInterface $logger, \Keboola\Json\Structure $structure, $nestedArraysAsJson, $strict)
- $logger - a logger, use `NullLogger` if no logger is used.
- $structure - a representation of JSON structure .
- $nestedArraysAsJson - if true, then nested arrays will be encoded as JSON strings. 
    If false (default), the conversion will fail.  
- $strict - if true, then JSON node data types will be checked more strictly (int, string, ...).

# \Keboola\Json\Parser
Parses JSON data into CSV files.

## __construct($analyzer, $definitions = [])
- $definitions - optional array with results from previous process.
- $analyzer - instance of analyzer class.

## process($data, $type, $parentId)
- $data - array of objects retrieved from JSON data.
- $type - is used for naming the resulting table(s).
- $parentId - either a string, which will be saved in a JSON_parentId column, or an array 
    with "column_name" => "value", which will name the column(s) by array key provided.
- If the data is analyzed, it is stored in Cache and **NOT PARSED** until the `getCsvFiles()` 
    method is called.

## getCsvFiles()
- returns a list of \Keboola\CsvTable\Table objects with parse results

# Parse characteristics
The analyze function loops through each row of an array (generally an array of results) and passes 
the row into `analyzeRow()` method. If the row only contains a scalar, it's stored in a "data" 
column. If the row is an object, each of the object's variables will be used as a column 
name, and its values are analyzed:
- if it is a scalar, it'll be saved as a value of that column.
- if it is an array, it'll be passed to `analyze()` to create a new table, linked by a generated `JSON_parentId` column
- if it is another object, it'll be parsed recursively to `analyzeRow()`, with its variable 
names prepended by current objects' name, e.g.:
```
"parent": {
    "child" : "value1"
}
```
will result in a `parent_child` column with a string type of "value1".
