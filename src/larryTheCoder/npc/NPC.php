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

use pocketmine\{
	Player, Server
};
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\{
	Item, ItemFactory
};
use pocketmine\level\Level;
use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\{
	AddPlayerPacket,
	DataPacket,
	MoveEntityAbsolutePacket,
	PlayerListPacket,
	PlayerSkinPacket,
	RemoveEntityPacket,
	types\PlayerListEntry
};
use pocketmine\utils\UUID;


/**
 * This is an NPC class that used by Particles
 * Which is a hack to force the server to NOT
 * SAVE THE ENTITY to disk
 *
 * @package larryTheCoder\npc
 */
class NPC extends Particle {

	public $entityId = -1;
	public $invisible = false;
	/** @var Skin */
	public $skin;
	/** @var float */
	public $yaw = 0, $pitch = 0;
	/** @var Level */
	public $level;
	/** @var UUID */
	public $uuid;

	/**
	 * @param Vector3 $pos
	 * @param Level $level
	 */
	public function __construct(Vector3 $pos, Level $level){
		parent::__construct($pos->x, $pos->y, $pos->z);
		$this->level = $level;
		$this->uuid = UUID::fromRandom();
		$this->skin = new Skin("Standard_Custom", str_repeat("\x00", 8192));
		$this->skin->debloatGeometryData();
	}

	/**
	 * Changes the entity's yaw and pitch to make it look at the specified Vector3 position. For mobs, this will cause
	 * their heads to turn.
	 *
	 * @param Player $target
	 */
	public function lookAt(Player $target): void{
		$horizontal = sqrt(($target->x - $this->x) ** 2 + ($target->z - $this->z) ** 2);
		$vertical = ($target->y - $this->y) + 0.6; // 0.6 is the player offset.
		$this->pitch = -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down

		$xDist = $target->x - $this->x;
		$zDist = $target->z - $this->z;
		$this->yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
		if($this->yaw < 0){
			$this->yaw += 360.0;
		}
		$this->updateMovement($target);
	}

	public function updateMovement(Player $player){
		$pk = new MoveEntityAbsolutePacket();

		$pk->entityRuntimeId = $this->entityId;
		$pk->position = $this->asVector3()->add(0, 1.6);

		$pk->xRot = $this->pitch;
		$pk->yRot = $this->yaw; //TODO: head yaw
		$pk->zRot = $this->yaw;

		$player->sendDataPacket($pk);
	}

	public function setSkin(?Skin $skin){
		$this->skin = $skin;
		if($skin === null){
			$skin = $this->skin = new Skin("Standard_Custom", str_repeat("\x00", 8192));
		}
		$skin->debloatGeometryData();

		$hasSpawned = [];
		foreach($this->level->getChunkPlayers($this->getX() >> 4, $this->getZ() >> 4) as $player){
			if($player->isOnline()){
				$hasSpawned[$player->getLoaderId()] = $player;
			}
		}

		$skinPk = new PlayerSkinPacket();
		$skinPk->uuid = $this->uuid;
		$skinPk->skin = $this->skin;
		$p[] = $skinPk;
		Server::getInstance()->broadcastPacket($hasSpawned, $skinPk);
	}

	/**
	 * @return DataPacket|DataPacket[]
	 */
	public function encode(){
		$p = [];

		if($this->entityId === -1){
			$this->entityId = Entity::$entityCount++;
		}else{
			$pk0 = new RemoveEntityPacket();
			$pk0->entityUniqueId = $this->entityId;

			$p[] = $pk0;
		}

		$name = "";

		$add = new PlayerListPacket();
		$add->type = PlayerListPacket::TYPE_ADD;
		$add->entries = [PlayerListEntry::createAdditionEntry($this->uuid, $this->entityId, $name, $this->skin)];
		$p[] = $add;

		$pk = new AddPlayerPacket();
		$pk->uuid = $this->uuid;
		$pk->username = $name;
		$pk->entityRuntimeId = $this->entityId;
		$pk->position = $this->asVector3(); // TODO: check offset
		$pk->item = ItemFactory::get(Item::AIR, 0, 0);

		$flags = (
			1 << Entity::DATA_FLAG_IMMOBILE
		);
		$pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.5],
		];

		$p[] = $pk;

		$remove = new PlayerListPacket();
		$remove->type = PlayerListPacket::TYPE_REMOVE;
		$remove->entries = [PlayerListEntry::createRemovalEntry($this->uuid)];
		$p[] = $remove;

		return $p;
	}

	public function remove(){
		$pk0 = new RemoveEntityPacket();
		$pk0->entityUniqueId = $this->entityId;

		Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk0);
	}

	public function showToPlayer(Player $player){
		$pk = new AddPlayerPacket();
		$pk->uuid = $this->uuid;
		$pk->username = "";
		$pk->entityRuntimeId = $this->entityId;
		$pk->position = $this->asVector3(); //TODO: check offset
		$pk->item = ItemFactory::get(Item::AIR, 0, 0);

		$pk->metadata = [
			Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.75] //zero causes problems on debug builds
		];

		$player->sendDataPacket($pk);

		if($this->skin !== null){
			$skinPk = new PlayerSkinPacket();
			$skinPk->uuid = $this->uuid;
			$skinPk->skin = $this->skin;
			$player->sendDataPacket($skinPk);
		}
	}

	public function inLevel(Entity $entity): bool{
		return $entity->getLevel()->getId() === $this->level->getId();
	}
}