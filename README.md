# Keboola PHP Temp service

## Usage

```php
    use Keboola\Temp\Temp;
	$temp = new Temp('prefix');
    $tempFile = $temp->createTmpFile('suffix');
```
