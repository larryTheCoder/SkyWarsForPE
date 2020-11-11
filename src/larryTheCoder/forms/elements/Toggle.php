<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

use pocketmine\form\FormValidationException;
use function is_bool;

class Toggle extends Element {
	/** @var bool */
	protected $default;

	public function __construct(string $text, bool $default = false){
		parent::__construct($text);
		$this->default = $default;
	}

	/**
	 * @return bool
	 */
	public function getValue(): bool{
		return parent::getValue();
	}

	/**
	 * @return bool
	 */
	public function hasChanged(): bool{
		return $this->default !== $this->value;
	}

	/**
	 * @return bool
	 */
	public function getDefault(): bool{
		return $this->default;
	}

	/**
	 * @return string
	 */
	public function getType(): string{
		return "toggle";
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeElementData(): array{
		return [
			"default" => $this->default,
		];
	}

	/**
	 * @param mixed $value
	 */
	public function validate($value): void{
		if(!is_bool($value)){
			throw new FormValidationException("Expected bool, got " . gettype($value));
		}
	}
}