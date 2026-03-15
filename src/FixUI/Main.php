<?php

declare(strict_types=1);

namespace FixUI;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Durable;
use pocketmine\item\Item;

use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase{

    public function onEnable(): void{
        $this->saveDefaultConfig();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            $sender->sendMessage("Run this in-game.");
            return true;
        }

        if($command->getName() === "fix"){
            $this->openRepairMenu($sender);
        }

        return true;
    }

    private function openRepairMenu(Player $player): void{

        $items = $this->getDamagedItems($player);

        if(count($items) === 0){
            $player->sendMessage($this->color($this->getConfig()->getNested("messages.no-items")));
            return;
        }

        $names = [];
        foreach($items as $item){
            $names[] = $item->getName();
        }

        $form = new CustomForm(function(Player $player, $data) use ($items){
            if($data === null){
                return;
            }

            $item = $items[$data[0]] ?? null;

            if($item !== null){
                $this->confirmRepair($player, $item);
            }
        });

        $form->setTitle($this->color($this->getConfig()->getNested("form.title")));
        $form->addLabel($this->color($this->getConfig()->getNested("form.description")));
        $form->addDropdown("Damaged Items", $names);

        $player->sendForm($form);
    }

    private function confirmRepair(Player $player, Item $item): void{

        $cost = $this->getConfig()->get("repair-cost-xp");

        if($player->hasPermission("fixui.bypass")){
            $cost = 0;
        }

        $message = str_replace("{xp}", (string)$cost, $this->getConfig()->getNested("form.confirm-message"));

        $form = new SimpleForm(function(Player $player, $data) use ($item, $cost){

            if($data === null){
                return;
            }

            if($data === 0){
                $this->repairItem($player, $item, $cost);
            }else{
                $player->sendMessage($this->color($this->getConfig()->getNested("messages.cancelled")));
            }
        });

        $form->setTitle($this->color($this->getConfig()->getNested("form.confirm-title")));
        $form->setContent($this->color($message));

        $form->addButton("&aConfirm");
        $form->addButton("&cCancel");

        $player->sendForm($form);
    }

    private function repairItem(Player $player, Item $item, int $cost): void{

        if($cost > 0 && $player->getXpManager()->getXpLevel() < $cost){
            $player->sendMessage($this->color($this->getConfig()->getNested("messages.not-enough-xp")));
            return;
        }

        $inv = $player->getInventory();

        foreach($inv->getContents() as $slot => $invItem){

            if($invItem->equals($item)){

                if($invItem instanceof Durable){

                    if($cost > 0){
                        $player->getXpManager()->subtractXpLevels($cost);
                    }

                    $invItem->setDamage(0);
                    $inv->setItem($slot, $invItem);

                    $player->sendMessage($this->color($this->getConfig()->getNested("messages.repaired")));
                    return;
                }
            }
        }
    }

    private function getDamagedItems(Player $player): array{

        $items = [];

        foreach($player->getInventory()->getContents() as $item){

            if($item instanceof Durable && $item->getDamage() > 0){
                $items[] = $item;
            }
        }

        return $items;
    }

    private function color(string $text): string{
        return str_replace("&", "§", $text);
    }
}
