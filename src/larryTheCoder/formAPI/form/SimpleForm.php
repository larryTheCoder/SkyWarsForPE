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

use larryTheCoder\formAPI\element\ElementButton;
use larryTheCoder\formAPI\element\ElementButtonImageData;
use larryTheCoder\formAPI\response\FormResponseSimple;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;

class SimpleForm extends Form {

	const IMAGE_TYPE_PATH = 0;
	const IMAGE_TYPE_URL = 1;

	/** @var int */
	public $id;
	/** @var string */
	public $playerName;
	/** @var array */
	private $data = [];
	/** @var string */
	private $content = "";
	/** @var ElementButton[] */
	private $buttons;

	/**
	 * @param int $id
	 */
	public function __construct(int $id){
		parent::__construct($id);
		$this->data["type"] = "form";
		$this->data["title"] = "";
		$this->data["content"] = $this->content;
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
	 * @return string
	 */
	public function getContent(): string{
		return $this->data["content"];
	}

	/**
	 * @param string $content
	 */
	public function setContent(string $content): void{
		$this->data["content"] = $content;
	}

	/**
	 * @param string $text
	 * @param int $imageType
	 * @param string $imagePath
	 */
	public function addButton(string $text, int $imageType = -1, string $imagePath = ""): void{
		$content = ["text" => $text];
		if($imageType !== -1){
			$content["image"]["type"] = $imageType === 0 ? "path" : "url";
			$content["image"]["data"] = $imagePath;
		}
		$this->data["buttons"][] = $content;
		$this->buttons[] = new ElementButton($text, new ElementButtonImageData(strval($imageType), $imagePath));
	}

	public function getResponseModal(): FormResponseSimple{
		return new FormResponseSimple($this, $this->buttons);
	}
}
