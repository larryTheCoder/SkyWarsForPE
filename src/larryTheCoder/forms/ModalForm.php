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
use pocketmine\{form\FormValidationException, Player, utils\Utils};
use function gettype;
use function is_bool;

class ModalForm extends Form {
	/** @var string */
	protected $text;
	/** @var string */
	private $yesButton;
	/** @var string */
	private $noButton;
	/** @var Closure */
	private $onSubmit;

	/**
	 * @param string $title
	 * @param string $text
	 * @param Closure $onSubmit
	 * @param string $yesButton
	 * @param string $noButton
	 */
	public function __construct(string $title, string $text, Closure $onSubmit, string $yesButton = "gui.yes", string $noButton = "gui.no"){
		parent::__construct($title);
		$this->text = $text;
		$this->yesButton = $yesButton;
		$this->noButton = $noButton;
		Utils::validateCallableSignature(function(Player $player, bool $response): void{
		}, $onSubmit);
		$this->onSubmit = $onSubmit;
	}

	/**
	 * @param string $title
	 * @param string $text
	 * @param Closure $onConfirm
	 *
	 * @return ModalForm
	 */
	public static function createConfirmForm(string $title, string $text, Closure $onConfirm): self{
		Utils::validateCallableSignature(function(Player $player): void{
		}, $onConfirm);

		return new self($title, $text, function(Player $player, bool $response) use ($onConfirm): void{
			if($response){
				$onConfirm($player);
			}
		});
	}

	/**
	 * @return string
	 */
	final public function getType(): string{
		return self::TYPE_MODAL;
	}

	/**
	 * @return string
	 */
	public function getYesButtonText(): string{
		return $this->yesButton;
	}

	/**
	 * @return string
	 */
	public function getNoButtonText(): string{
		return $this->noButton;
	}

	/**
	 * @return mixed[]
	 */
	protected function serializeFormData(): array{
		return [
			"content" => $this->text,
			"button1" => $this->yesButton,
			"button2" => $this->noButton,
		];
	}

	final public function handleResponse(Player $player, $data): void{
		FormQueue::removeForm($player);

		if(!is_bool($data)){
			throw new FormValidationException("Expected bool, got " . gettype($data));
		}
		($this->onSubmit)($player, $data);
	}
}