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

namespace larryTheCoder\formAPI;

use larryTheCoder\formAPI\event\FormRespondedEvent;
use larryTheCoder\formAPI\form\{CustomForm, Form, ModalForm, SimpleForm};
use larryTheCoder\SkyWarsPE;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class FormAPI implements Listener {

	/** @var int */
	public $formCount = 0;
	/** @var Form[] */
	public $forms = [];

	public function __construct(SkyWarsPE $plugin){
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}

	/**
	 * @return CustomForm
	 */
	public function createCustomForm(): CustomForm{
		$this->formCount++;
		$form = new CustomForm($this->formCount);
		$this->forms[$this->formCount] = $form;

		return $form;
	}

	public function createModalForm(): ModalForm{
		$this->formCount++;
		$form = new ModalForm($this->formCount);
		$this->forms[$this->formCount] = $form;

		return $form;
	}

	public function createSimpleForm(): SimpleForm{
		$this->formCount++;
		$form = new SimpleForm($this->formCount);
		$this->forms[$this->formCount] = $form;

		return $form;
	}

	/**
	 * @param DataPacketReceiveEvent $ev
	 * @priority MONITOR
	 */
	public function onPacketReceived(DataPacketReceiveEvent $ev): void{
		$pk = $ev->getPacket();
		if($pk instanceof ModalFormResponsePacket){
			$player = $ev->getPlayer();
			$formId = $pk->formId;
			if(isset($this->forms[$formId])){
				if(!$this->forms[$formId]->isRecipient($player)){
					return;
				}
				$modal = $this->forms[$formId]->getResponseModal();
				$modal->setData(trim($pk->formData));
				$event = new FormRespondedEvent($player, $this->forms[$formId], $modal);
				try{
					$event->call();
				}catch(\ReflectionException $e){
				}
				$ev->setCancelled();
			}
		}
	}

	/**
	 * @param PlayerQuitEvent $ev
	 */
	public function onPlayerQuit(PlayerQuitEvent $ev){
		$player = $ev->getPlayer();
		/**
		 * @var int $id
		 * @var Form $form
		 */
		foreach($this->forms as $id => $form){
			if($form->isRecipient($player)){
				unset($this->forms[$id]);
				break;
			}
		}
	}

}
