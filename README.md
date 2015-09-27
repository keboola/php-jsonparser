# Json Parser

## Description
Parse Json strings into CSV files
Creates multiple tables from a single JSON, if said JSON contains numbered arrays
Uses Keboola\CsvFile for results

## Usage

```php
    use Keboola\Json\Parser;
	$parser = Parser::create(new \Monolog\Logger('json-parser'));
	$file = file_get_contents("some/data.json");
	$json = json_decode($file);

	$parser->process($json);

	$results = $parser->getCsvFiles(); // array of CsvFile objects
```


# Parser\Json

Analyzes and parses JSON data into n*CSV files.

## create(\Monolog\Logger $logger, $struct, $analyzeRows)
- $struct should contain an array with results from previous analyze() calls (called automatically by process())
- $analyzeRows determines, how many rows of data (counting only the "root" level of each Json)  will be analyzed [default -1 for infinite]

## process($data, $type, $parentId)
- Expects an array of results as the $data parameter
- $type is used for naming the resulting table(s)
- The $parentId may be either a string, which will be saved in a JSON_parentId column, or an array with "column_name" => "value", which will name the column(s) by array key provided
- Checks whether the data needs to be analyzed, and either analyzes or parses it into `$this->tables[$type]` ($type is polished to comply with SAPI naming requirements)
- If the data is analyzed, it is stored in Cache and **NOT PARSED** until $this->getCsvFiles() is called

## getCsvFiles()
- returns a list of \Common\Table objects with parse results

# Parse characteristics
The analyze function loops through each row of an array (generally an array of results) and passes the row into analyzeRow() method. If the row only contains a string, it's stored in a "data" column, otherwise the row should usually be an object, so each of the object's variables will be used as a column name, and it's value analysed:
- if it's a scalar, it'll be saved as a value of that column.
- if it's another object, it'll be parsed recursively to analyzeRow(), with it's variable names prepended by current object's name
	- example:
			"parent": {
				"child" : "value1"
			}
			will result into a "parent_child" column with a string type of "value1"
- if it's an array, it'll be passed to analyze() to create a new table, linked by JSON_parentId
