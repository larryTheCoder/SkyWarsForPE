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

namespace larryTheCoder\utils\fireworks;

use larryTheCoder\utils\fireworks\entity\FireworksRocket;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;
use pocketmine\utils\Random;

class Fireworks extends Item {

	public $spread = 5.0;

	public function __construct($meta = 0){
		parent::__construct(self::FIREWORKS, $meta, "Fireworks");
	}

	public static function ToNbt(FireworksData $data, Position $pos, int $yaw, int $pitch): CompoundTag{
		$value = [];
		$root = new CompoundTag();
		$tag = new CompoundTag();
		$tag->setByteArray("FireworkColor", strval($data->fireworkColor));
		$tag->setByteArray("FireworkFade", strval($data->fireworkFade));
		$tag->setByte("FireworkFlicker", ($data->fireworkFlicker ? 1 : 0));
		$tag->setByte("FireworkTrail", ($data->fireworkTrail ? 1 : 0));
		$tag->setByte("FireworkType", $data->fireworkType);
		$value[] = $tag;

		$explosions = new ListTag("Explosions", $value, NBT::TAG_Compound);
		$root->setTag(new CompoundTag("Fireworks",
				[
					$explosions,
					new ByteTag("Flight", $data->flight),
				])
		);

		$root->setTag(new ListTag("Pos", [
			new DoubleTag("", $pos->x),
			new DoubleTag("", $pos->y),
			new DoubleTag("", $pos->z),
		]));
		$root->setTag(new ListTag("Motion", [
			new DoubleTag("", 0.0),
			new DoubleTag("", 0.0),
			new DoubleTag("", 0.0),
		]));
		$root->setTag(new ListTag("Rotation", [
			new FloatTag("", $yaw),
			new FloatTag("", $pitch),
		]));

		return $root;
	}

	public function onActivate(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector): bool{
		$random = new Random();
		$yaw = $random->nextBoundedInt(360);
		$pitch = -1 * (float)(90 + ($random->nextFloat() * $this->spread - $this->spread / 2));
		$nbt = Entity::createBaseNBT($blockReplace->add(0.5, 0, 0.5), null, $yaw, $pitch);
		/** @var CompoundTag $tags */
		$tags = $this->getNamedTagEntry("Fireworks");
		if(!is_null($tags)){
			$nbt->setTag($tags);
		}

		$rocket = new FireworksRocket($player->getLevel(), $nbt, $this, $player);
		$player->getLevel()->addEntity($rocket);

		if($rocket instanceof Entity){
			if($player->isSurvival()){
				--$this->count;
			}
			$rocket->spawnToAll();

			return true;
		}

		return false;
	}
}
