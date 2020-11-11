<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

use JsonSerializable;
use pocketmine\form\FormValidationException;
use function is_int;

abstract class Element implements JsonSerializable {
	/** @var string */
	protected $text;
	/** @var mixed */
	protected $value;

	/**
	 * @param string $text
	 */
	public function __construct(string $text){
		$this->text = $text;
	}

	/**
	 * @return mixed
	 */
	public function getValue(){
		return $this->value;
	}

	/**
	 * @param mixed $value
	 */
	public function setValue($value): void{
		$this->value = $value;
	}

	/**
	 * @return array<string, mixed>
	 */
	final public function jsonSerialize(): array{
		$array = ["text" => $this->getText()];
		if($this->getType() !== null){
			$array["type"] = $this->getType();
		}

		return $array + $this->serializeElementData();
	}

	/**
	 * @return string
	 */
	public function getText(): string{
		return $this->text;
	}

	/**
	 * @return string|null
	 */
	abstract public function getType(): ?string;

	/**
	 * @return string[]
	 */
	abstract public function serializeElementData(): array;

	/**
	 * @param mixed $value
	 */
	public function validate($value): void{
		if(!is_int($value)){
			throw new FormValidationException("Expected int, got " . gettype($value));
		}
	}
}