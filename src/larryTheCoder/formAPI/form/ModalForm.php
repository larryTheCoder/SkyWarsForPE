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

namespace larryTheCoder\formAPI\form;

use larryTheCoder\formAPI\response\FormResponseModal;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;

class ModalForm extends Form {

	/** @var array */
	public $data = [];

	public function __construct($id){
		parent::__construct($id);
		$this->data["type"] = "modal";
		$this->data["title"] = "Modal Form";
		$this->data["content"] = "";
		$this->data["button1"] = "true";
		$this->data["button2"] = "false";
	}

	/**
	 * @return int
	 */
	public function getId(): int{
		return $this->id;
	}

	public function getTitle(){
		return $this->data["title"];
	}

	public function setTitle(string $title){
		$this->data["title"] = $title;
	}

	public function getContent(){
		return $this->data["content"];
	}

	public function setContent(string $content){
		$this->data["content"] = $content;
	}

	public function getButton1(){
		return $this->data["button1"];
	}

	public function setButton1(string $button1){
		$this->data["button1"] = $button1;
	}

	public function getButton2(){
		return $this->data["button2"];
	}

	public function setButton2(string $button2){
		$this->data["button2"] = $button2;
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

	public function getResponseModal(): FormResponseModal{
		return new FormResponseModal($this);
	}
}