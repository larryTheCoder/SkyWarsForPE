<?php
/*
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2020 larryTheCoder and contributors
 *
 * Permission is hereby granted to any persons and/or organizations
 * using this software to copy, modify, merge, publish, and distribute it.
 * Said persons and/or organizations are not allowed to use the software or
 * any derivatives of the work for commercial use or any other means to generate
 * income, nor are they allowed to claim this software as their own.
 *
 * The persons and/or organizations are also disallowed from sub-licensing
 * and/or trademarking this software without explicit permission from larryTheCoder.
 *
 * Any persons and/or organizations using this software must disclose their
 * source code and have it publicly available, include this license,
 * provide sufficient credit to the original authors of the project (IE: larryTheCoder),
 * as well as provide a link to the original project.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS FOR A PARTICULAR
 * PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
 * USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

declare(strict_types = 1);

namespace larryTheCoder\forms;

use larryTheCoder\forms\elements\{Dropdown, Element, Input, Label, Slider, StepSlider, Toggle};
use pocketmine\form\FormValidationException;
use function array_shift;
use function get_class;

class CustomFormResponse {
	/** @var Element[] */
	private $elements;

	/**
	 * @param Element[] $elements
	 */
	public function __construct(array $elements){
		$this->elements = $elements;
	}

	/**
	 * @param string $expected
	 *
	 * @return Element|mixed
	 * @internal
	 *
	 */
	public function tryGet(string $expected = Element::class){ //why PHP still hasn't templates???
		if(($element = array_shift($this->elements)) instanceof Label){
			return $this->tryGet($expected); //remove useless element
		}elseif($element === null || !($element instanceof $expected)){
			throw new FormValidationException("Expected a element with of type $expected, got " . get_class($element));
		}

		return $element;
	}

	/**
	 * @return Dropdown
	 */
	public function getDropdown(): Dropdown{
		return $this->tryGet(Dropdown::class);
	}

	/**
	 * @return Input
	 */
	public function getInput(): Input{
		return $this->tryGet(Input::class);
	}

	/**
	 * @return Slider
	 */
	public function getSlider(): Slider{
		return $this->tryGet(Slider::class);
	}

	/**
	 * @return StepSlider
	 */
	public function getStepSlider(): StepSlider{
		return $this->tryGet(StepSlider::class);
	}

	/**
	 * @return Toggle
	 */
	public function getToggle(): Toggle{
		return $this->tryGet(Toggle::class);
	}

	/**
	 * @return Element[]
	 */
	public function getElements(): array{
		return $this->elements;
	}

	/**
	 * @return mixed[]
	 */
	public function getValues(): array{
		$values = [];
		foreach($this->elements as $element){
			if($element instanceof Label){
				continue;
			}
			$values[] = $element instanceof Dropdown ? $element->getSelectedOption() : $element->getValue();
		}

		return $values;
	}
}