<?php

declare(strict_types=1);

namespace DataLion\HopperFilterAltay;


use DataLion\HopperFilterAltay\tiles\BetterHopperTile;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Tile;

class Main extends PluginBase{

	public function onEnable() : void{
		$this->getLogger()->info("Enabled");


        try {
            Tile::registerTile(BetterHopperTile::class, [Tile::HOPPER, "minecraft:hopper"]);
            $this->getLogger()->info("Registered tile");
        } catch (\ReflectionException $e) {
            $this->getLogger()->error("Could not register custom Hopper Tile");
            $this->setEnabled(false);

        }

    }


	public function onDisable() : void{
		$this->getLogger()->info("Disabled");
	}
}
