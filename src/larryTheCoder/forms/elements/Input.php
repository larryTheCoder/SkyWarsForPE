<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

use pocketmine\form\FormValidationException;
use function is_string;

class Input extends Element {
	/** @var string */
	private $placeholder;
	/** @var string */
	private $default;

	/**
	 * @param string $text
	 * @param string $placeholder
	 * @param string $default
	 */
	public function __construct(string $text, string $placeholder, string $default = ""){
		parent::__construct($text);
		$this->placeholder = $placeholder;
		$this->default = $default;
	}

	/**
	 * @return string
	 */
	public function getValue(): string{
		return parent::getValue();
	}

	/**
	 * @return string
	 */
	public function getPlaceholder(): string{
		return $this->placeholder;
	}

	/**
	 * @return string
	 */
	public function getDefault(): string{
		return $this->default;
	}

	/**
	 * @return string
	 */
	public function getType(): string{
		return "input";
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeElementData(): array{
		return [
			"placeholder" => $this->placeholder,
			"default"     => $this->default,
		];
	}

	/**
	 * @param mixed $value
	 */
	public function validate($value): void{
		if(!is_string($value)){
			throw new FormValidationException("Expected string, got " . gettype($value));
		}
	}
}