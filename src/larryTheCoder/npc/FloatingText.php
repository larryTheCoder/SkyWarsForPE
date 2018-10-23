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

namespace larryTheCoder\npc;

use pocketmine\entity\{
	Entity, Skin
};
use pocketmine\item\{
	Item, ItemFactory
};
use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\{
	AddPlayerPacket, PlayerListPacket, RemoveEntityPacket, types\PlayerListEntry
};
use pocketmine\Server;
use pocketmine\utils\UUID;

class FloatingText extends Particle {

	protected $text;
	protected $title;
	protected $entityId;
	protected $invisible = false;

	/**
	 * @param Vector3 $pos
	 * @param string $text
	 * @param string $title
	 */
	public function __construct(Vector3 $pos, string $text, string $title = ""){
		parent::__construct($pos->x, $pos->y, $pos->z);
		$this->text = $text;
		$this->title = $title;
	}

	public function getText(): string{
		return $this->text;
	}

	public function setText(string $text): void{
		$this->text = $text;
	}

	public function getTitle(): string{
		return $this->title;
	}

	public function setTitle(string $title): void{
		$this->title = $title;
	}

	public function isInvisible(): bool{
		return $this->invisible;
	}

	public function setInvisible(bool $value = true){
		$this->invisible = $value;
	}

	public function remove(){
		$pk0 = new RemoveEntityPacket();
		$pk0->entityUniqueId = $this->entityId;

		Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk0);
	}

	public function encode(){
		$p = [];

		if($this->entityId === null){
			$this->entityId = Entity::$entityCount++;
		}else{
			$pk0 = new RemoveEntityPacket();
			$pk0->entityUniqueId = $this->entityId;

			$p[] = $pk0;
		}

		if(!$this->invisible){
			$uuid = UUID::fromRandom();

			$add = new PlayerListPacket();
			$add->type = PlayerListPacket::TYPE_ADD;
			$add->entries = [PlayerListEntry::createAdditionEntry($uuid, $this->entityId, "", new Skin("Standard_Custom", \str_repeat("\x00", 8192)))];
			$p[] = $add;

			$pk = new AddPlayerPacket();
			$pk->uuid = $uuid;
			$pk->username = $this->title . ($this->text !== "" ? "\n" . $this->text : "");
			$pk->entityRuntimeId = $this->entityId;
			$pk->position = $this->asVector3(); //TODO: check offset
			$pk->item = ItemFactory::get(Item::AIR, 0, 0);

			$flags = (
				1 << Entity::DATA_FLAG_IMMOBILE
			);
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01] //zero causes problems on debug builds
			];

			$p[] = $pk;

			$remove = new PlayerListPacket();
			$remove->type = PlayerListPacket::TYPE_REMOVE;
			$remove->entries = [PlayerListEntry::createRemovalEntry($uuid)];
			$p[] = $remove;
		}

		return $p;
	}
}
