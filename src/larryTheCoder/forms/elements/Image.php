<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

use JsonSerializable;

class Image implements JsonSerializable {
	public const TYPE_URL = "url";
	public const TYPE_PATH = "path";
	/** @var string */
	private $type;
	/** @var string */
	private $data;

	/**
	 * @param string $data
	 * @param string $type
	 */
	public function __construct(string $data, string $type = self::TYPE_URL){
		$this->type = $type;
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getType(): string{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getData(): string{
		return $this->data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array{
		return [
			"type" => $this->type,
			"data" => $this->data,
		];
	}
}