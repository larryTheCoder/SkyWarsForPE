<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

use pocketmine\form\FormValidationException;

class Label extends Element {
	/**
	 * @return string
	 */
	public function getType(): string{
		return "label";
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeElementData(): array{
		return [];
	}

	public function validate($value): void{
		if($value !== null){
			throw new FormValidationException("Expected null, got " . gettype($value));
		}
	}
}