<?php

namespace Keboola\Json;

use Keboola\Utils\Utils;

class Cache {
	protected $data = array();
	protected $temp;
	protected $readPosition = 0;

	public function store($data) {
		if(
			ini_get('memory_limit') != "-1" &&
			memory_get_usage() > (Utils::return_bytes(ini_get('memory_limit')) * 0.75)
		) {
			// cache
			if (empty($this->temp)) {
				$this->temp = fopen("php://temp/", 'w+');
			}

			fseek($this->temp, 0, SEEK_END);
			fputs($this->temp, base64_encode(serialize($data)) . PHP_EOL);
		} else {
			$this->data[] = $data;
		}
	}

	public function getNext() {
		if (!empty($this->temp) && !feof($this->temp)) {
			// keep the file position in case the file's been written to
			fseek($this->temp, $this->readPosition);
			$data = fgets($this->temp);
			$this->readPosition += strlen($data);
			return unserialize(base64_decode($data));
		} elseif (!empty($this->temp) && feof($this->temp)) {
			fclose($this->temp);
			unset($this->temp);
		}

		return array_shift($this->data);
	}
}
