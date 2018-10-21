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

declare(strict_types = 1);

namespace larryTheCoder\formAPI\form;

use larryTheCoder\formAPI\element\{
	Element, ElementDropdown, ElementInput, ElementLabel, ElementSlider, ElementStepSlider, ElementToggle
};
use larryTheCoder\formAPI\response\FormResponseCustom;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;

class CustomForm extends Form {

	/** @var int */
	public $id;
	/** @var string */
	public $playerName;
	/** @var Element[] */
	public $elements = [];
	/** @var array */
	private $data = [];

	/**
	 * @param int $id
	 */
	public function __construct(int $id){
		parent::__construct($id);
		$this->data["type"] = "custom_form";
		$this->data["title"] = "";
		$this->data["content"] = [];
	}

	/**
	 * @return int
	 */
	public function getId(): int{
		return $this->id;
	}

	/**
	 * @param Player $player
	 */
	public function sendToPlayer(Player $player): void{
		$pk = new ModalFormRequestPacket();
		$pk->formId = $this->id;
		$pk->formData = json_encode($this->data);
		$player->dataPacket($pk);
		$this->playerName = $player->getName();
	}

	/**
	 * @param string $title
	 */
	public function setTitle(string $title): void{
		$this->data["title"] = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string{
		return $this->data["title"];
	}

	/**
	 * @param string $text
	 */
	public function addLabel(string $text): void{
		$this->addContent(["type" => "label", "text" => $text]);
		$this->elements[] = new ElementLabel($text);
	}

	/**
	 * @param array $content
	 */
	private function addContent(array $content): void{
		$this->data["content"][] = $content;
	}

	/**
	 * @param string $text
	 * @param bool|null $default
	 */
	public function addToggle(string $text, bool $default = false): void{
		$content = ["type" => "toggle", "text" => $text];
		if($default !== null){
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->elements[] = new ElementToggle($text, $default);
	}

	/**
	 * @param string $text
	 * @param int $min
	 * @param int $max
	 * @param int $step
	 * @param int $default
	 */
	public function addSlider(string $text, int $min, int $max, int $step = -1, int $default = -1): void{
		$content = ["type" => "slider", "text" => $text, "min" => $min, "max" => $max];
		if($step !== -1){
			$content["step"] = $step;
		}
		if($default !== -1){
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->elements[] = new ElementSlider($text, $min, $max, $step, $default);
	}

	/**
	 * @param string $text
	 * @param array $steps
	 * @param int $defaultIndex
	 */
	public function addStepSlider(string $text, array $steps, int $defaultIndex = -1): void{
		$content = ["type" => "step_slider", "text" => $text, "steps" => $steps];
		if($defaultIndex !== -1){
			$content["default"] = $defaultIndex;
		}
		$this->addContent($content);
		$this->elements[] = new ElementStepSlider($text, $steps, $defaultIndex);
	}

	/**
	 * @param string $text
	 * @param array $options
	 * @param int $default
	 */
	public function addDropdown(string $text, array $options, int $default = 0): void{
		$this->addContent(["type" => "dropdown", "text" => $text, "options" => $options, "default" => $default]);
		$this->elements[] = new ElementDropdown($text, $options, $default);
	}

	/**
	 * @param string $text
	 * @param string $placeholder
	 * @param string $default
	 */
	public function addInput(string $text, string $placeholder = "", string $default = ""): void{
		$this->addContent(["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default]);
		$this->elements[] = new ElementInput($text, $placeholder, $default);
	}

	public function getResponseModal(): FormResponseCustom{
		return new FormResponseCustom($this);
	}
}