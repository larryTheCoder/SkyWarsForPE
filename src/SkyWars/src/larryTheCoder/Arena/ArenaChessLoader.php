<?php

namespace larryTheCoder\Arena;

use pocketmine\item\Item;
/**
 * Description of ArenaChessLoader
 *
 * @author Windows 7
 */
class ArenaChessLoader {
    
    // COPY from svile plugin SkyWars-Pocketmine
    public static function getChestContents() {
        $items = ['armor' => [
                [ Item::LEATHER_CAP,
                    Item::LEATHER_TUNIC,
                    Item::LEATHER_PANTS,
                    Item::LEATHER_BOOTS
                ],
                [ Item::GOLD_HELMET,
                    Item::GOLD_CHESTPLATE,
                    Item::GOLD_LEGGINGS,
                    Item::GOLD_BOOTS
                ],
                [ Item::CHAIN_HELMET,
                    Item::CHAIN_CHESTPLATE,
                    Item::CHAIN_LEGGINGS,
                    Item::CHAIN_BOOTS
                ],
                [ Item::IRON_HELMET,
                    Item::IRON_CHESTPLATE,
                    Item::IRON_LEGGINGS,
                    Item::IRON_BOOTS
                ],
                [ Item::DIAMOND_HELMET,
                    Item::DIAMOND_CHESTPLATE,
                    Item::DIAMOND_LEGGINGS,
                    Item::DIAMOND_BOOTS
                ]],
            //WEAPONS
            'weapon' => [
                [ Item::WOODEN_SWORD,
                    Item::WOODEN_AXE,
                ],
                [ Item::GOLD_SWORD,
                    Item::GOLD_AXE
                ],
                [ Item::STONE_SWORD,
                    Item::STONE_AXE
                ],
                [ Item::IRON_SWORD,
                    Item::IRON_AXE
                ],
                [ Item::DIAMOND_SWORD,
                    Item::DIAMOND_AXE
                ]],
            //FOOD
            'food' => [[Item::RAW_PORKCHOP,
            Item::RAW_CHICKEN,
            Item::MELON_SLICE,
            Item::COOKIE
                ],
                [Item::RAW_BEEF,
                    Item::CARROT
                ],
                [Item::APPLE,
                    Item::GOLDEN_APPLE
                ],
                [Item::BEETROOT_SOUP,
                    Item::BREAD,
                    Item::BAKED_POTATO
                ],
                [Item::MUSHROOM_STEW,
                    Item::COOKED_CHICKEN
                ],
                [Item::COOKED_PORKCHOP,
                    Item::STEAK,
                    Item::PUMPKIN_PIE
                ],
            ],
            //THROWABLE
            'throwable' => [[Item::BOW,
            Item::ARROW
                ],
                [Item::SNOWBALL
                ],
                [Item::EGG
                ]
            ],
            //BLOCKS
            'block' => [Item::STONE,
                Item::WOODEN_PLANK,
                Item::COBBLESTONE,
                Item::DIRT
            ],
            //OTHER
            'other' => [[Item::WOODEN_PICKAXE,
            Item::GOLD_PICKAXE,
            Item::STONE_PICKAXE,
            Item::IRON_PICKAXE,
            Item::DIAMOND_PICKAXE
                ],
                [Item::STICK,
                    Item::STRING
                ]
            ]
        ];

        $templates = [];
        for ($i = 0; $i < 10; $i++) {

            $armorq = mt_rand(0, 1);
            $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
            $armor1 = [$armortype[\mt_rand(0, (\count($armortype) - 1))], 1];
            if ($armorq) {
                $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
                $armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            } else {
                $armor2 = array(0, 1);
            }
            unset($armorq, $armortype);

            $weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
            $weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
            unset($weapontype);

            $ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
            $food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
            unset($ftype);

            $add = mt_rand(0, 1);
            if ($add) {
                $tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
                if (count($tr) == 2) {
                    $throwable1 = array($tr[1], mt_rand(10, 20));
                    $throwable2 = array($tr[0], 1);
                } else {
                    $throwable1 = array(0, 1);
                    $throwable2 = array($tr[0], mt_rand(5, 10));
                }
                $other = array(0, 1);
            } else {
                $throwable1 = array(0, 1);
                $throwable2 = array(0, 1);
                $ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
                $other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
            }
            unset($add, $tr, $ot);

            $block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 64);

            $contents = array(
                $armor1,
                $armor2,
                $weapon,
                $food,
                $throwable1,
                $throwable2,
                $block,
                $other
            );
            shuffle($contents);
            $fcontents = array(
                mt_rand(1, 2) => array_shift($contents),
                mt_rand(3, 5) => array_shift($contents),
                mt_rand(6, 10) => array_shift($contents),
                mt_rand(11, 15) => array_shift($contents),
                mt_rand(16, 17) => array_shift($contents),
                mt_rand(18, 20) => array_shift($contents),
                mt_rand(21, 25) => array_shift($contents),
                mt_rand(26, 27) => array_shift($contents),
            );
            $templates[] = $fcontents;
        }

        shuffle($templates);
        return $templates;
    }
    
}
