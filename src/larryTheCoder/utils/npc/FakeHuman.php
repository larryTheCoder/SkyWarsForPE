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

namespace larryTheCoder\utils\npc;

use larryTheCoder\arena\api\translation\TranslationContainer;
use larryTheCoder\SkyWarsPE;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\Player;
use pocketmine\Server;

/**
 * Faster implementation of Fake Entities or well known as NPCs.
 * Uses BatchPacket to send data to the player much faster.
 */
class FakeHuman extends Human {

	/** @var FloatingTextParticle|null */
	private $particleCache = null;
	/** @var string */
	private $messageCache = "";
	/** @var int */
	private $levelPedestal;

	public function __construct(Level $level, CompoundTag $nbt, int $pedestalLevel){
		$nbtNew = new BigEndianNBTStream();
		$compound = $nbtNew->readCompressed(@stream_get_contents(SkyWarsPE::getInstance()->getResource("metadata-fix.dat")));
		if(!($compound instanceof CompoundTag)){
			throw new \RuntimeException("Unable to read skin metadata from SkyWarsForPE resources folder, corrupted build?");
		}

		$skinTag = $compound->getCompoundTag("Skin");
		$skin = new Skin(
			$skinTag->getString("Name"),
			$skinTag->hasTag("Data", StringTag::class) ? $skinTag->getString("Data") : $skinTag->getByteArray("Data"), //old data (this used to be saved as a StringTag in older versions of PM)
			$skinTag->getByteArray("CapeData", ""),
			$skinTag->getString("GeometryName", ""),
			$skinTag->getByteArray("GeometryData", "")
		);
		$this->setSkin($skin);

		parent::__construct($level, $nbt);

		$this->setCanSaveWithChunk(false);
		$this->setImmobile(false);
		$this->setScale(0.8);
		$this->setNameTagAlwaysVisible(false);

		$this->levelPedestal = $pedestalLevel;

		$this->fetchData();
	}

	public function attack(EntityDamageEvent $source): void{
		$source->setCancelled();
	}

	public function onUpdate(int $currentTick): bool{
		if($this->isClosed()){
			return false;
		}

		if($currentTick % 3 === 0){
			// Look at the player, and sent the packet only
			// to the player who looked at it
			foreach($this->getLevel()->getPlayers() as $playerName){
				if($playerName->distance($this) <= 15){
					$this->lookAtInto($playerName);
				}
			}
		}elseif($currentTick % 200 === 0){
			$this->fetchData();
		}

		return true;
	}

	private function fetchData(): void{
		$pedestal = SkyWarsPE::getInstance()->getPedestals();
		if($pedestal === null && !$this->isFlaggedForDespawn()){
			$this->flagForDespawn();

			return;
		}

		$object = $pedestal->getPedestalObject($this->levelPedestal);


		// Send the skin (Only use the .dat skin data)
		if(file_exists(Server::getInstance()->getDataPath() . "players/" . strtolower($object[0]) . ".dat")){
			$nbt = Server::getInstance()->getOfflinePlayerData($object[0]);
			$skin = $nbt->getCompoundTag("Skin");
			if($skin !== null){
				$skin = new Skin(
					$skin->getString("Name"),
					$skin->hasTag("Data", StringTag::class) ? $skin->getString("Data") : $skin->getByteArray("Data"), //old data (this used to be saved as a StringTag in older versions of PM)
					$skin->getByteArray("CapeData", ""),
					$skin->getString("GeometryName", ""),
					$skin->getByteArray("GeometryData", "")
				);
				try{
					$skin->validate();
					$this->setSkin($skin);
				}catch(\Exception $ignored){
				}
			}
		}

		// The text packets
		$msg1 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$object[0], $this->levelPedestal, $object[1]], TranslationContainer::getTranslation(null, 'top-winner-1'));
		$msg2 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$object[0], $this->levelPedestal, $object[1]], TranslationContainer::getTranslation(null, 'top-winner-2'));
		$msg3 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$object[0], $this->levelPedestal, $object[1]], TranslationContainer::getTranslation(null, 'top-winner-3'));
		$array = [$msg1, $msg2, $msg3];
		$this->sendText($array);
	}

	/**
	 * Changes the entity's yaw and pitch to make it look at the specified Vector3 position. For mobs, this will cause
	 * their heads to turn.
	 *
	 * @param Player $target
	 */
	public function lookAtInto(Player $target): void{
		$horizontal = sqrt(($target->x - $this->x) ** 2 + ($target->z - $this->z) ** 2);
		$vertical = ($target->y - $this->y) + 0.55;
		$this->pitch = -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down

		$xDist = $target->x - $this->x;
		$zDist = $target->z - $this->z;
		$this->yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
		if($this->yaw < 0){
			$this->yaw += 360.0;
		}
		$this->updateMovementInto($target);
	}

	private function updateMovementInto(Player $player): void{
		// (byte)((pkg.x == -1 ? 1 : 0) | (pkg.x == 1 ? 2 : 0) | (pkg.y == -1 ? 4 : 0) | (pkg.y == 1 ? 8 : 0) | (pkg.pckp ? 16 : 0) | (pkg.thrw ? 32 : 0) | (pkg.jmp ? 64 : 0))
		$pk = new MoveActorAbsolutePacket();

		$pk->entityRuntimeId = $this->id;
		$pk->position = $this->getOffsetPosition($this);

		$pk->xRot = $this->pitch;
		$pk->yRot = $this->yaw;
		$pk->zRot = $this->yaw;

		$player->sendDataPacket($pk);
	}

	public function close(): void{
		$this->despawnText($this->getViewers());

		parent::close();
	}

	public function spawnTo(Player $player): void{
		parent::spawnTo($player);

		// Resend the text packet to the player
		$this->sendText([], true, $player);
	}

	public function despawnFrom(Player $player, bool $send = true): void{
		parent::despawnFrom($player, $send);

		$this->despawnText([$player]);
	}

	/**
	 * @param Player[] $player
	 */
	public function despawnText(array $player): void{
		$pk = [];

		$this->particleCache->setInvisible(true);

		$pk = array_merge($pk, $this->particleCache->encode());

		$this->particleCache->setInvisible(false);

		Server::getInstance()->batchPackets($player, $pk);
	}

	// TODO: Use entity default spawn

	/**
	 * @param string[] $messages
	 * @param bool $resend
	 * @param Player|null $player
	 */
	public function sendText(array $messages, bool $resend = false, ?Player $player = null): void{
		$pk = [];

		if($resend && $this->particleCache !== null){
			$pk = array_merge($pk, $this->particleCache->encode());
		}else{
			if($this->particleCache === null){
				$this->particleCache = $particle = new FloatingTextParticle($this->getOffsetPosition($this), $msg = implode("\n", $messages));
				$pk = array_merge($pk, $particle->encode());

				$this->messageCache = $msg;
			}else{
				$msg = implode("\n", $messages);
				if($this->messageCache === $msg){
					return;
				}

				$this->messageCache = $msg;

				$this->particleCache->setText($msg);
				$pk = array_merge($pk, $this->particleCache->encode());
			}
		}

		if(!empty($pk)){
			if($player !== null){
				foreach($pk as $packet){
					$player->batchDataPacket($packet);
				}
			}else{
				Server::getInstance()->batchPackets($this->getViewers(), $pk);
			}
		}
	}
}