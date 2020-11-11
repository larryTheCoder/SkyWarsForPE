<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

use InvalidArgumentException;
use pocketmine\form\FormValidationException;
use function is_float;
use function is_int;

class Slider extends Element {
	/** @var float */
	protected $min;
	/** @var float */
	protected $max;
	/** @var float */
	protected $step = 1.0;
	/** @var float */
	protected $default;

	/**
	 * @param string $text
	 * @param float $min
	 * @param float $max
	 * @param float $step
	 * @param float|null $default
	 */
	public function __construct(string $text, float $min, float $max, float $step = 1.0, ?float $default = null){
		parent::__construct($text);
		if($this->min > $this->max){
			throw new InvalidArgumentException("Slider min value should be less than max value");
		}
		$this->min = $min;
		$this->max = $max;
		if($default !== null){
			if($default > $this->max or $default < $this->min){
				throw new InvalidArgumentException("Default must be in range $this->min ... $this->max");
			}
			$this->default = $default;
		}else{
			$this->default = $this->min;
		}
		if($step <= 0){
			throw new InvalidArgumentException("Step must be greater than zero");
		}
		$this->step = $step;
	}

	/**
	 * @return float|int
	 */
	public function getValue(){
		return parent::getValue();
	}

	/**
	 * @return float
	 */
	public function getMin(): float{
		return $this->min;
	}

	/**
	 * @return float
	 */
	public function getMax(): float{
		return $this->max;
	}

	/**
	 * @return float
	 */
	public function getStep(): float{
		return $this->step;
	}

	/**
	 * @return float
	 */
	public function getDefault(): float{
		return $this->default;
	}

	/**
	 * @return string
	 */
	public function getType(): string{
		return "slider";
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeElementData(): array{
		return [
			"min"     => $this->min,
			"max"     => $this->max,
			"default" => $this->default,
			"step"    => $this->step,
		];
	}

	public function validate($value): void{
		if(!is_int($value) && !is_float($value)){
			throw new FormValidationException("Expected int or float, got " . gettype($value));
		}
	}
}