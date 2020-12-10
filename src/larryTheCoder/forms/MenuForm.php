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

use Closure;
use larryTheCoder\forms\elements\Button;
use pocketmine\{form\FormValidationException, Player, utils\Utils};
use function array_merge;
use function is_string;

class MenuForm extends Form {
	/** @var Button[] */
	protected $buttons = [];
	/** @var string */
	protected $text;
	/** @var Closure|null */
	private $onSubmit;
	/** @var Closure|null */
	private $onClose;

	/**
	 * @param string $title
	 * @param string $text
	 * @param Button[]|string[] $buttons
	 * @param Closure|null $onSubmit
	 * @param Closure|null $onClose
	 */
	public function __construct(string $title, string $text = "", array $buttons = [], ?Closure $onSubmit = null, ?Closure $onClose = null){
		parent::__construct($title);
		$this->text = $text;
		$this->append(...$buttons);
		$this->setOnSubmit($onSubmit);
		$this->setOnClose($onClose);
	}

	/**
	 * @param string $text
	 *
	 * @return self
	 */
	public function setText(string $text): self{
		$this->text = $text;

		return $this;
	}

	/**
	 * @param Button|string ...$buttons
	 *
	 * @return self
	 */
	public function append(...$buttons): self{
		if(isset($buttons[0]) && is_string($buttons[0])){
			$buttons = Button::createFromList(...$buttons);
		}
		$this->buttons = array_merge($this->buttons, $buttons);

		return $this;
	}

	/**
	 * @param Closure|null $onSubmit
	 *
	 * @return self
	 */
	public function setOnSubmit(?Closure $onSubmit): self{
		if($onSubmit !== null){
			Utils::validateCallableSignature(function(Player $player, Button $selected): void{
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
	 * @return string
	 */
	final public function getType(): string{
		return self::TYPE_MENU;
	}

	/**
	 * @return mixed[]
	 */
	protected function serializeFormData(): array{
		return [
			"buttons" => $this->buttons,
			"content" => $this->text,
		];
	}

	final public function handleResponse(Player $player, $data): void{
		FormQueue::removeForm($player);

		if($data === null){
			if($this->onClose !== null){
				($this->onClose)($player, $data);
			}
		}elseif(is_int($data)){
			if(!isset($this->buttons[$data])){
				throw new FormValidationException("Button with index $data does not exist");
			}
			if($this->onSubmit !== null){
				$button = $this->buttons[$data];
				$button->setValue($data);
				($this->onSubmit)($player, $button);
			}
		}else{
			throw new FormValidationException("Expected int or null, got " . gettype($data));
		}
	}
}