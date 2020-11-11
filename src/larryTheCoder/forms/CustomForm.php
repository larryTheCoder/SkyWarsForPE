<?php
/**
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

use Closure;
use larryTheCoder\forms\elements\Element;
use pocketmine\{form\FormValidationException, Player, utils\Utils};
use function array_merge;
use function gettype;
use function is_array;

class CustomForm extends Form {

	/** @var Element[] */
	protected $elements = [];
	/** @var Closure|null */
	private $onSubmit = null;
	/** @var Closure|null */
	private $onClose = null;

	/**
	 * @param string $title
	 * @param Closure|null $onSubmit
	 * @param Closure|null $onClose
	 */
	public function __construct(string $title, ?Closure $onSubmit = null, ?Closure $onClose = null){
		parent::__construct($title);

		$this->setOnSubmit($onSubmit);
		$this->setOnClose($onClose);
	}

	/**
	 * @param Closure|null $onSubmit
	 *
	 * @return self
	 */
	public function setOnSubmit(?Closure $onSubmit): self{
		if($onSubmit !== null){
			Utils::validateCallableSignature(function(Player $player, CustomFormResponse $response): void{
			}, $onSubmit);
			$this->onSubmit = $onSubmit;
		}

		return $this;
	}

	/**
	 * @param Closure|null $onClose
	 *
	 * @return self
	 */
	public function setOnClose(?Closure $onClose): self{
		if($onClose !== null){
			Utils::validateCallableSignature(function(Player $player): void{
			}, $onClose);
			$this->onClose = $onClose;
		}

		return $this;
	}

	/**
	 * @param Element ...$elements
	 *
	 * @return $this
	 */
	public function append(Element ...$elements): self{
		$this->elements = array_merge($this->elements, $elements);

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getType(): string{
		return self::TYPE_CUSTOM_FORM;
	}

	final public function handleResponse(Player $player, $data): void{
		if($data === null){
			if($this->onClose !== null){
				($this->onClose)($player);
			}
		}elseif(is_array($data)){
			foreach($data as $index => $value){
				if(!isset($this->elements[$index])){
					throw new FormValidationException("Element at index $index does not exist");
				}
				$element = $this->elements[$index];
				$element->validate($value);
				$element->setValue($value);
			}
			($this->onSubmit)($player, new CustomFormResponse($this->elements));
		}else{
			throw new FormValidationException("Expected array or null, got " . gettype($data));
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function serializeFormData(): array{
		return ["content" => $this->elements];
	}
}