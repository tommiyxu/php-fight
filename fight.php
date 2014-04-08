<?php

    include_once "FightManager.php"

    $player_cards = include_once "data.player_cards.php";

    Card::$cards = include_once "data.cards.php";

    Card::$levels = include_once "data.card_levels.php"

    Card::$skills = include_once "data.skills.php"

    $soldiers = FightManager::CreateSoldiers($player_cards);

    $monsters = FightManager::RandMonsters(count($player_cards));

    $ft1 = new FightTeam(FightTeamType::Player,  $soldiers);
    $ft2 = new FightTeam(FightTeamType::Monster, $monsters);

    $fight = FightManager::getInstance();
    $fight->setTeams($ft1, $ft2);

    while($fight->fighting()) {
        ;
    }

    if ($_REQUEST['format'] == "html") {
        header("Content-type: text/html; charset=utf-8");
        echo Record::toString();
    } else {
        echo json_encode(Record::getRecords());
    }
?>
