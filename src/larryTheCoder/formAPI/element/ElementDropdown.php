<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2018 larryTheCoder and contributors
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

namespace larryTheCoder\formAPI\element;


class ElementDropdown extends Element {

	/** @var string */
	private $text = "";
	/** @var array|string[] */
	private $options;
	/** @var int */
	private $defaultOptionIndex = 0;

	/**
	 * @param string $text
	 * @param string[] $options
	 * @param int $defaultOption
	 */
	public function __construct(string $text, array $options = [], int $defaultOption = 0){
		$this->text = $text;
		$this->options = $options;
		$this->defaultOptionIndex = $defaultOption;
	}

	public function getDefaultOptionIndex(){
		return $this->defaultOptionIndex;
	}

	public function setDefaultOptionIndex(int $index){
		if($index >= count($this->options)) return;
		$this->defaultOptionIndex = $index;
	}

	public function getOptions(): array{
		return $this->options;
	}

	public function getText(){
		return $this->text;
	}

	public function setText(String $text){
		$this->text = $text;
	}

	public function addOption(String $option, bool $isDefault = false){
		$this->options[] = $option;
		if($isDefault) $this->defaultOptionIndex = count($this->options) - 1;
	}

}