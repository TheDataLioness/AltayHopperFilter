<?php

namespace DataLion\HopperFilterAltay\tiles;


use pocketmine\block\Block;

use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\FurnaceInventory;
use pocketmine\inventory\HopperInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;

use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\tile\ContainerTrait;
use pocketmine\tile\Hopper;

use pocketmine\tile\NameableTrait;


class BetterHopperTile extends Hopper{
    use NameableTrait {
        addAdditionalSpawnData as addNameSpawnData;
    }
    use ContainerTrait;

    /** @var HopperInventory */
    protected $inventory;
    /** @var int */
    protected $transferCooldown = 1;
    /** @var AxisAlignedBB */
    protected $pullBox;

    public const TAG_TRANSFER_COOLDOWN = "TransferCooldown";

    public function __construct(Level $level, CompoundTag $nbt){
        parent::__construct($level, $nbt);

        $this->pullBox = new AxisAlignedBB($this->x, $this->y, $this->z, $this->x + 1, $this->y + 1.5, $this->z + 1);

        $this->scheduleUpdate();

    }

    protected function readSaveData(CompoundTag $nbt) : void{
        $this->transferCooldown = $nbt->getInt(self::TAG_TRANSFER_COOLDOWN, 8);

        $this->inventory = new HopperInventory($this);

        $this->loadName($nbt);
        $this->loadItems($nbt);
    }

    protected function writeSaveData(CompoundTag $nbt) : void{
        $nbt->setInt(self::TAG_TRANSFER_COOLDOWN, $this->transferCooldown);

        $this->saveItems($nbt);
        $this->saveName($nbt);
    }

//    public function close() : void{
//        if(!$this->closed){
//            $this->inventory->removeAllViewers(true);
//            $this->inventory = null;
//            parent::close();
//        }
//    }

    public function getInventory(){
        return $this->inventory;
    }

    public function getRealInventory(){
        return $this->inventory;
    }

//    public function getDefaultName() : string{
//        return "BetterHopper";
//    }

    protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
        $this->addNameSpawnData($nbt);
    }

    public function onUpdate() : bool{

        if($this->isOnTransferCooldown()){
            $this->transferCooldown--;
        }else{

            $transfer = false;

            if(!$this->isEmpty()){

                $transfer = $this->transferItemOut();
            }

            if(!$this->isFull()){

                $transfer = $this->pullItemFromTop() or $transfer;

            }

            if($transfer){

                $this->setTransferCooldown(8);
            }
        }

        return true;
    }

    public function isEmpty() : bool{
        return count($this->inventory->getContents()) === 0;
    }

    public function isFull() : bool{
        $full = true;
        foreach($this->inventory->getContents() as $slot => $item){
            if($item->getMaxStackSize() !== $item->getCount()){
                $full = false;
            }
        }
        return $full;
    }

    public function transferItemOut() : bool{

        $tile = $this->level->getTile($this->getSide($this->getBlock()->getDamage()));


        //CAN FILL DOWN
        $downtile = $this->level->getTile($this->down());
        $canFillDown = false;
        if(!is_null($downtile)){
            if($downtile instanceof BetterHopperTile){
                $filter = BetterHopperTile::getFilter($downtile->getBlock());
                if(!is_null($filter)){
                    $itemsCanGet = self::getFirstFilterItem($filter, $this->getInventory());
                    if(!is_null($itemsCanGet)){
                        $canFillDown = true;
                    }
                }
            }
        }




        //ITEMOUT AND PULL CANT BE SAME
        if(($tile instanceof Hopper && $tile ===  $downtile)  || $canFillDown){
            return true;
        }elseif ($tile instanceof Hopper ){
            $targetInventory = $tile->getInventory();
            $itemFilter = self::getFilter($this->getBlock());
            if(!is_null($itemFilter)){
                $itemsCanGet = self::getFirstFilterItem($itemFilter, $this->getInventory());
                if(!is_null($itemsCanGet)){
                    if($this->inventory->canAddItem($itemsCanGet)){
                        $targetInventory->addItem($itemsCanGet);
                        $this->inventory->removeItem($itemsCanGet);

                        return true;
                    }
                }
            }
        }

        if($tile instanceof InventoryHolder){
            $targetInventory = $tile->getInventory();





            foreach($this->inventory->getContents() as $slot => $item){



                $item->setCount(1);

                if($targetInventory->canAddItem($item)){
                    $targetInventory->addItem($item);
                    $this->inventory->removeItem($item);

                    return true;
                }
            }
        }

        return false;
    }

    public function pullItemFromTop() : bool{

        $tile = $this->level->getTile($this->up());

        if($tile instanceof InventoryHolder){

            $inv = $tile->getInventory();
            $itemFilter = self::getFilter($this->getBlock());
            if(!is_null($itemFilter)){
                $itemsCanGet = self::getFirstFilterItem($itemFilter, $inv);
                if(!is_null($itemsCanGet)){
                    if($this->inventory->canAddItem($itemsCanGet)){
                        $this->inventory->addItem($itemsCanGet);
                        $inv->removeItem($itemsCanGet);

                        return true;
                    }
                }
            }
            foreach($inv->getContents() as $slot => $item){
                if(!is_null($itemFilter)){
                    if(!in_array(ItemFactory::get($item->getId(), $item->getDamage()), $itemFilter)){
                        continue;
                    }
                }

                if($inv instanceof FurnaceInventory){
                    if($slot !== 2){
                        continue;
                    }
                }
                $item->setCount(1);



                if($this->inventory->canAddItem($item)){
                    $this->inventory->addItem($item);
                    $inv->removeItem($item);

                    return true;
                }
            }
        }else{
            /** @var ItemEntity $entity */
            foreach(array_filter($this->level->getNearbyEntities($this->pullBox), function(Entity $entity) : bool{
                return $entity instanceof ItemEntity and !$entity->isFlaggedForDespawn();
            }) as $entity){
                $filter = BetterHopperTile::getFilter($this->getBlock());
                if(!is_null($filter)){
                    if(in_array(ItemFactory::get($entity->getItem()->getId(), $entity->getItem()->getDamage()), $filter)){
                        $item = $entity->getItem();
                        if($this->inventory->canAddItem($item)){

                            $this->inventory->addItem($item);

                            $entity->flagForDespawn();

                            return true;
                        }
                    }
                }else{
                    $item = $entity->getItem();
                    if($this->inventory->canAddItem($item)){
                        $this->inventory->addItem($item);

                        $entity->flagForDespawn();

                        return true;
                    }
                }


            }
        }

        return false;
    }

    public function isOnTransferCooldown() : bool{
        return $this->transferCooldown > 0;
    }

    public function setTransferCooldown(int $cooldown){
        $this->transferCooldown = $cooldown;
    }

    /**
     * @return Item[] array or null
     */
    private static function getFilter(Block $b): ?array
    {
        $items = [];
        $sides = $b->getHorizontalSides();
        foreach ($sides as $sb){


                $signtile = $sb->getLevel()->getTile($sb->asVector3());

                if($signtile instanceof \pocketmine\tile\Sign){
                    $text = $signtile->getText();


                    if(($text[0] === "[HopperFilter]" || $text[0] === "§a§l[HopperFilter]") && isset($text[1])){
                        $signtile->setLine(0,"§a§l[HopperFilter]");
                        $itemNames = [];
                        $itemNames[] = $text[1];
                        $itemNames[] = $text[2];
                        $itemNames[] = $text[3];


                        foreach ($itemNames as $key => $name){
                            try{
                                $items[] = ItemFactory::fromString($name);
                            }catch(\InvalidArgumentException $e){

                                $signtile->setLine($key + 1, "§c".str_replace("§c", "", $name), true);

                            }

                        }



                    }
                }

        }

        if(sizeof($items) > 0){
            return $items;
        }

        return null;
    }


    /**
     * @param Item[] $filter
     * @param Inventory $inv
     * @return Null | Item
     */
    private static function getFirstFilterItem(array $filter, Inventory $inv): ?Item{
        $firstitem = null;
        $content = $inv->getContents();
        foreach ($content as $slot => $item){
            if($inv instanceof FurnaceInventory){
                if($slot !== 2){
                    continue;
                }
            }

            if(in_array(Item::get($item->getId(), $item->getDamage()), $filter) && is_null($firstitem)) $firstitem = $item->setCount(1);
        }
        return $firstitem;
    }
}