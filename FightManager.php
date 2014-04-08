<?php
class RecordType {
    const GetBuff = 0;
    const IncHP   = 1;
    const DecHP   = 2;
    const RoundBegin  = 3;
    const RoundEnd    = 4;
    const Ended    = 5; //
    const Killed   = 6;
    const Next     = 7; //
    const Info     = 8;
    const Attack   = 9;
    const Winner   = 10;
    const Skill    = 11; // 使用技能
    const Buffing  = 12; // buff中
};

class Record {
    static $records = array();
    static $winner  = "";
    static $count   = 0;
    static $type2colors = array(
        RecordType::GetBuff    => 'LightCoral',
        RecordType::IncHP      => 'green',
        RecordType::DecHP      => 'maroon',
        RecordType::RoundBegin => 'aqua',
        RecordType::RoundEnd   => 'aqua',
        RecordType::Ended      => 'purple',
        RecordType::Killed     => 'fuchsia',
        RecordType::Next       => 'teal',
        RecordType::Info       => 'blue',
        RecordType::Attack     => 'olive',
        RecordType::Winner     => 'navy',
        RecordType::Skill      => 'LightCoral',
        RecordType::Buffing    => 'LightCoral',
    );

   /* static function log($record) {
        self::$records[] = $record;
    }*/

    static function add($type, $params, $info) {
        if ($_REQUEST['format'] == "html") {
            $info = '<font color="'.self::$type2colors[$type].'">' .$info. '</font>';
            if ($type == RecordType::RoundEnd) {
                $info .= "<br />";
            }
        }
        if (isset($params['hp']) ) {
            $params['hp'] = (int)$params['hp'];
        }
        $record = array(
            'type'   => $type,
            'params' => (object)$params,
            'info'   => $info,
        );

        if ($_REQUEST['format'] == "html") {
            self::$records[] = $info;
        } else {
            self::$records[] = $record;
        }
    }
    static function getRecords() {
        return array(
            "count"  => self::$count."",
            "winner" => self::$winner,
            "infos"  => self::$records,
        );
    }
    static function toString() {
        return ( nl2br(implode("<br />", self::$records)));
    }

}

class EventType {
    const Attack      = 0; // 攻击
    const Attacked    = 1; // 被攻击
    const Hurt        = 2; // 被伤害
    const Crit        = 3; // 暴击
    const Crited      = 4; // 被暴击
    const Death       = 5; // 死亡时
    const RoundEnd    = 6; // 回合结束
    const FightStart  = 7; // 战斗开始
    const RoundStart  = 8; // 回合开始
    const PlayedStart = 9; // 出场
};


class Subject
{
    private $_skills = array();
    private $_buffs  = array();

    public function hasBuff($id) {
         return isset($this->_buffs[$id]);
    }

    public function getPlayerCardID() {
        return 0;
    }

    public function addBuff($buff) {
        if ( !isset($this->_buffs[$buff->getID()]) ) {
            $name = $this->getNameWithTeam();
            $is_debuff = BuffID::IsDebuff($buff->getID())? "Debuff":"Buff";
            $buff_name = BuffID::ID2Name($buff->getID());
            Record::add(RecordType::GetBuff, array("dest_sno" => $this->getSNO()), "$name 获得一个 $buff_name $is_debuff");
            $this->_buffs[$buff->getID()] = $buff;
            $buff->exec();
        }
    }

    public function addSkill($skill) {
        //Log::write("add Observer:" . get_class($observer), Log::INFO);
        // 覆盖原来的技能
        $this->_skills[$skill->getID()] = $skill;
    }

    public function delBuff($buff) {
        unset($this->_buffs[$buff->getID()]);
    }

    public function notify($event, $target=null) {
        $params = array(
            'source' => $this,
            'target' => $target,
        );
        foreach($this->_buffs as $buff) {
            $buff->update($event, $params);
        }

        if ($event != EventType::RoundEnd) {
            foreach($this->_skills as $skill) {
                $skill->update($event, $params);
            }
        }
    }
}


// Observer interface
abstract class ISkill {
    // @event  EventType
    // @params array ('tabname' => 'tablename', 'data' => data)
    static $event2str = array (
       EventType::Attack   => "攻击",
       EventType::Attacked => "被攻击",
       EventType::RoundEnd   => "回合",
    );
    abstract protected function _update($event, $params);

    public function update($event, $params) {

       // Record::add( self::$event2str[$event] . "{$event}事件 $classname\n\n");
        $this->_update($event, $params);
    }

    protected $_id = 0;
    protected $_name = "";
    protected $_subject = NULL;

    public function __construct($subject, $params=array()) {
        $this->_subject = $subject;
        $this->_id   = $subject->getSkillID();
        if (isset($params['name'])) {
            $this->_name = $params['name'];
        }
        $this->_parse_params($params['params']);
    }

    protected $_params = array();
    const SkillParams = 0;
    const BuffParams  = 1;
    public function getSubject() {
        return $this->_subject;
    }
    protected function _parse_params($str, $index=self::SkillParams) {
         if (empty($str)) return;
         $str = trim($str);
         if (strpos($str, "-") !== false) {
             $str_params = explode("-", $str);
             foreach($str_params as $i => $str_param) {
                 $this->_parse_params($str_param, $i);
             }
             return;
         }

         $str_params = explode(" ", $str);
         foreach($str_params as $str_param) {
             if (empty($str_param)) continue;
             if (strpos($str_param, ":") === false) {
                 echo ("param'{$str_param}' error.\n");
                 continue;
             }
             $p = explode(":", $str_param, 2);
             if (strpos($p[1], ",") !== false) {
                 $p[1] = explode(",", $p[1]);
             }
             $this->_params[$index][$p[0]] = $p[1];
         }
    }

    public function isAttack($params) {
        if ($this->_subject == $params['source']) {
            return true;
        }
        return false;
    }

    public function getID() {
        return $this->_id;
    }

    public function getName() {
        return $this->_name;
    }

    public function getTeamName() {
        return FightManager::getInstance()->getTeam($this->_subject->getSNO())->getName();
    }
}

class FightHelper {
    static public function IsHit($rate) {
        $rand = mt_rand(1, 100);
         //Record::add(RecordType::Info, array(), " rate:$rate rand:{$rand} ");
        if (0 < $rand && $rand < $rate*100) {
            //Record::add(RecordType::Info, array(), " rate:$rate rand:{$rand} ");
            return true;
        }

        return false;
    }

    static public function Notify($source, $target, $event) {
        $source->notify($event, $target);
        $event = ($event == EventType::Attack)? EventType::Attacked:$event;
        $target->notify($event, $source);
    }

    static public function HpLess($subject, $rate) {
        return self::HpCompare($subject, $rete, "LT");
    }

    static public function HpCompare($subject, $rate, $op) {
        $hp     = $subject->getHP();
        $max_hp = $subject->getProp("hp");
        switch($op) { // LT|ELT|GT|EGT
        case "LT":
            return $hp < $max_hp * $rate;
        case "GT":
            return $hp > $max_hp * $rate;
        case "EGT":
            return $hp >= $max_hp * $rate;
        case "ELT":
            return $hp <= $max_hp * $rate;
        default:
            return false;
        }
        return false;
    }
}

class Buff extends ISkill {
    protected $_round = 999;
    protected $_count = 0;
    protected $_name = "";
    public function __construct($subject, $params=array(), $id) {
        $this->_subject = $subject;
        $this->_id      = $id;
        $this->_round   = isset($params['CR'])? $params['CR']:1;
        unset($params["CR"]);
        $this->_params  = $params;
    }

    public function update($event, $params) {
        $name = $this->_subject->getNameWithTeam();
        $classname = get_class($this);
        if ($event == EventType::RoundEnd) {
            if ( $this->timeout($event) ) {
                // Record::add(RecordType::Info, array(), " $name {$this->_id} timeout _round:{$this->_round} ");
            } else{
                // Record::add(RecordType::Info, array(), " $name {$this->_id} count:{$this->_count} ");
            }
            return;
        }
        if ($event != EventType::RoundStart) {
            return true;
        }
        //Record::log( "{$name} $classname\n");
        $this->_update($event, $params);
    }

    public function exec() {
        SkillExecutor::execByBuff($this, "", $this->_params);
    }

    protected function _update($event, $params) {
        $params = array_merge($this->_params, $params);
        SkillExecutor::execByBuff($this, $event, $this->_params);
    }

    private function timeout($event) {
        $this->_count++;
        if ($this->_count == $this->_round) {
            $this->_subject->delBuff($this);
            return true;
        }
        return false;
    }
}

class BuffID {
    const Angry   = "Angry";
    const Burning = "Burning";
    const DecHP   = "DecHP";
    const Burn    = "Burn";
    const Excited = "Excited";
    const Vertigo = "Vertigo"; // TODO 眩晕 失去攻击能力
    const Weak    = "Weak";    // TODO 虚弱buff 攻击力降低
    const Protect = "Protect"; // TODO 保護buff 降低傷害

    static function ID2Name($id) {
        $names = array (
            self::Angry   => "愤怒",
    		self::Burning => "燃烧",
    		self::DecHP   => "愤怒",
    		self::Burn    => "燃烧",
    		self::Excited => "攻击提高",
    		self::Vertigo => "眩晕",
    		self::Weak    => "虚弱",
    		self::Protect => "伤害降低",
        );
        return isset($names[$id])? $names[$id]:"Unknow name";
    }

    static protected $_debuffs = array(
        BuffID::Burning,
        BuffID::DecHP,
        BuffID::Burn,
        BuffID::Vertigo,
        BuffID::Weak,
    );

    static public function IsDebuff($id) {
        return in_array($id, self::$_debuffs);
    }
};


// --- 技能命令字符串 说明----
// PBA                  when the Probability of Being Attacked(被攻击时几率)
// PA                where the Probability of Attack(攻击时几率)
// PD                Probability of Death(死亡时几率)
// PC                 Probability of Crit(暴击时几率)
// PPS                  Probability of Played Start(出场时的几率)
// PRS                 Probability of Round Start(回合开始时的概率)
// PFS                  Probability of Fight Start（战斗开始）
// PH                   when the Probability of Hurt(受伤害时的几率)

// IA1                  Increased Attack (攻击力提高)
// DA                   Decrease attack（攻击力降低)
// DD                   Decrease Damage(傷害降低百分比)
// SA                   Stop Attack(停止攻击) Vertigo(眩晕） 跌倒等造成角色当前回合停止攻击

// CR2                  Continuing Round(持续回合)

// D20,30,40,55,70      Damage(伤害)
// ED40,55,70,90,110    Extra Damage(额外伤害)

// IHP55,68,85,100,120  Increase of HP(加血)

// SHP                  Self HP 自身血量                条件变量
// EHP                  Enemy HP （敌人血量）           条件变量

// T0                   target（技能目标）T0 自己 T1敌人 第二个使用ST the Second Target 第三个TT the Third Target
// Buff                 Buff技能


// LT|ELT|GT|EGT        支持上午比较运算符
// -------------


class SkillExecutor
{
    static private $cmd2event = array (
        "PBA" => EventType::Attacked,
        "PA"  => EventType::Attack,
        "PC"  => EventType::Crit,
        "PD"  => EventType::Death,
        "PPS" => EventType::PlayedStart,
        "PRS" => EventType::RoundStart,
        "PFS" => EventType::FightStart,
        "PH"  => EventType::Hurt,
    );
    static public function _value($subject, $value) {
        if ( !is_array($value) ) {
            return $value;
        }

        $level = $subject->getLevel() - 1; // 0 is start

        if (!isset($value[$level])) {
            throw new Exception("value is error.".var_export($value, true));
        }

        return $value[$level];
    }
    static private function _is_event_hit($cmd, $value, $event) {
         if (isset(self::$cmd2event[$cmd])) {
            if ($event != self::$cmd2event[$cmd]) {
                return false;
            }
            if (!FightHelper::IsHit($value)) {
                //Record::add(RecordType::Info, array(), "$cmd IsHit($value) miss");
                return false;
            }
           // Record::add(RecordType::Info, array(), "$cmd IsHit($value) hit");
        }
        return true;
    }
    static public function execByBuff($buff, $event, $params) {
        foreach($params as $cmd => $value) {
            $new_value = self::_value($buff->getSubject(), $value);
            if ( !self::_is_event_hit($cmd, $value, $event) ) {
                return;
            }
            if ( !isset(self::$cmd2event[$cmd]) ) {
                self::_exec_common($buff->getSubject(), $event, $cmd, $new_value, $params);
            }
        }
    }

    static private function _exec_common($subject, $event, $cmd, $value, $params) {
        switch($cmd) {
            case "DA":
                $value = -$value;
            case "IA":
               // $subject = $skill->getSubject();
                //Record::add(RecordType::Info, array(), $subject->getNameWithTeam()."IA $value");
                $subject->addIncreaseAttackRate($value, "IA");
                break;
            case "DD":
                //Record::add(RecordType::Info, array(), $subject->getNameWithTeam()."DD $value");
                $subject->addDecreaseDamageRate($value, "DD");
                break;
            case "D":
                $subject->extraDamage($value, $params['target']);
                break;
            case "ED":
                $subject->extraDamage($value, $params['target']);
                break;
            case "IHP":
                $subject->restoreHP($value);
                break;
            case "SA":
                $subject->addStopAttackFlag($value);
                break;
            default:
                echo "unknow param '$cmd'<br>\n";
                break;
            } // end switch
    }

    static private function _hp_compare($subject, $expression) {
        list($op, $value) = explode(":", $expression);
        return FightHelper::HpCompare($subject, $value, $op);
    }

    static private $skill_record_is_added = false;
    static private function _add_record_skill($skill, $params) {
        if (!self::$skill_record_is_added) {
            $params = array("dest_sno" => $skill->getSubject()->getSNO(), "skill_id" => (int)$skill->getID());
            if ($skill->getID() == 5) { // 命运无常
                $params["card_id"] = (int)$skill->getSubject()->getID();
                $params["hp"] = (int)$skill->getSubject()->getProp("hp");
            }
            Record::add(RecordType::Skill, $params, $skill->getSubject()->getNameWithTeam()." 发动技能 ".$skill->getName());
            self::$skill_record_is_added = true;
        }
    }

    static public function execBySkill($skill, $event, $params) {
        $target = null; // buff target
        self::$skill_record_is_added = false;
        foreach($params[ISkill::SkillParams] as $cmd => $value) {
            switch($cmd) {
            case "T":
            case "ST":
            case "TT":
                    $target = ($value == "0")? $params['source'] : $params['target'];
                break;
            case "Buff":{
                    $buff = "Buff"; //$value . "Buff";
                    if (is_null($target)) {
                        throw new Exception("Buff target is null.");
                    }

                    self::_add_record_skill($skill, $params);
                    self::_process_buff_params($target, $skill, $params[ISkill::BuffParams]);
                    $target->addBuff(new $buff($target, $params[ISkill::BuffParams], $value));
                }
                break;
            case "SHP":
                if (!self::_hp_compare($params['source'], $value)) {
                    return;
                }
                break;
            case "EHP":
                if (!self::_hp_compare($params['target'], $value)) {
                    return;
                }
                break;
            default: {
                    if ( !self::_is_event_hit($cmd, $value, $event) ) {
                        return;
                    }

                    if ( !isset(self::$cmd2event[$cmd]) ) {
                        self::_add_record_skill($skill, $params);
                        $value = self::_value($skill->getSubject(), $value);
                        self::_exec_common($target, $event, $cmd, $value, $params);
                    }
                }
                break;
            } // end switch
        }// end foreach $params
    }

    // 如果buff 是一个debuff 需要根据发技能的人等级 提前确定debuff中为数组的value
    static function _process_buff_params($target, $skill, &$params) {
        if ($target == $skill->getSubject()) {
            return;
        }

        foreach($params as $cmd => $value) {
            $params[$cmd] = self::_value($skill->getSubject(), $value);
        }
    }
}

class SkillImpl extends ISkill {

    protected function _update($event, $params)
    {
        $params = array_merge($this->_params, $params);
        SkillExecutor::execBySkill($this, $event, $params);
    }
}

class Card extends Subject
{
    protected $_id    = 0;
    protected $_name  = "";
    protected $_hp    = 0;
    protected $_type  = 0;
    protected $_level = 0;
    protected $_skill_id = 0;
    protected $_player_card_id = 0;
    public function getPlayerCardID() {
        return $this->_player_card_id;
    }

    protected $_increase_attack_rates = array(); // increased伤害(攻击力)提高
    protected $_decrease_damage_rates = array(); // 减伤比例
    public function getID() {
        return $this->_id;
    }

    public function getLevel() {
        return $this->_level;
    }

    public function addIncreaseAttackRate($rate, $key) {
        $this->_increase_attack_rates[$key] = $rate;
    }

    public function calcDamageByIncreaseAttackRates($damage) {
        if (!empty($this->_increase_attack_rates)) {
            foreach($this->_increase_attack_rates as $rate) {
                $damage += round($damage * $rate);
                $info = ($rate > 0)? "攻击提高 ":"攻击降低 ";
                Record::add(RecordType::Info, array(), $this->getNameWithTeam() .$info.($rate * 100)."%");
            }
            $this->_increase_attack_rates = array(); // 清除
        }
        return $damage;
    }

    protected $_sno = ''; // fight sno

    // card_id, level
    public static $cards  = array(); // card_id => card_data
    public static $levels = array(); // card_id + level => card_level_data
    public static $skills = array(); // skill_id => skill_data

    public function loadSkill() {
        $params = self::$skills[$this->_skill_id];
        // TODO skill params
        $this->addSkill(new SkillImpl($this, $params));
    }

    //test
    function __construct($card_id) {
        $data = & self::$cards[$card_id];
        $this->_id   = $data['id'];
        $this->_name = $data['name'];
        $this->_type = $data['type'];
        $this->_skill_id = $data['skill_id'];
        $this->_hp   = self::$levels[$this->_id][$this->_level]["hp"];

        $this->loadSkill();
    }

    function getNameWithTeam() {
        return FightManager::getInstance()->getTeam($this->getSNO())->getName(). "的" . $this->getName();
    }

    function attack($target) {
        if ($this->isStopAttack())  {
            return 0;
        }

        $dec_hp = $this->getDamage($target->getType(), $target);
        $dec_hp = round($dec_hp);

        $target->decHP($dec_hp, $this);

        Record::add(RecordType::Attack, array('src_sno' => $this->getSNO(), 'dest_sno' => $target->getSNO(), "hp" => -$dec_hp),
                    $this->getNameWithTeam()  . " 伤害 ". $target->getNameWithTeam() . " {$dec_hp} 点！");

        FightHelper::Notify($this, $target, EventType::Attack);

        if ($this->_has_crit) {
            $this->notify(EventType::Crit, $target);
            $this->_has_crit = false;
        }

        return $dec_hp;
    }

    function getProp($name=NULL) {
        if ($name == NULL) {
            return self::$levels[$this->_id][$this->_level];
        }

        if (isset(self::$levels[$this->_id][$this->_level][$name]) ) {
            return self::$levels[$this->_id][$this->_level][$name];
        }
        return NULL;
    }

    protected $_stop_attack_flags = array();
    protected function isStopAttack() {
        if (empty($this->_stop_attack_flags)) {
            return false;
        }
        $this->notify_stop_flags();
        $this->_stop_attack_flags = array();
        return true;
    }

    protected function notify_stop_flags() {
        $name = $this->getNameWithTeam();
        foreach($this->_stop_attack_flags as $flag => $v) {
            $flag = BuffID::ID2Name($flag);
            Record::add(RecordType::Buffing, array("dest_sno" => $this->getSNO(), "buff"=> $flag), "$name $flag 无法攻击");
        }
    }

    public function addStopAttackFlag($flag) {
        $this->_stop_attack_flags[$flag] = 1;
    }

    public function addDecreaseDamageRate($rate, $key) {
        $this->_decrease_damage_rates[$key] = $rate;
    }

    public function calcDamageByDecreaseDamageRates($damage) {
        if (!empty($this->_decrease_damage_rates)) {
            foreach($this->_decrease_damage_rates as $rate) {
                $damage -= round($damage * $rate);
                Record::add(RecordType::Info, array(), $this->getNameWithTeam()." 技能减伤 ".($rate * 100)."%");
            }
            $this->_decrease_damage_rates = array(); // 清除
        }
        return $damage;
    }

    public function extraDamage($damage, $source) {
        Record::add(RecordType::DecHP, array("dest_sno" => $this->getSNO(), "hp" => $damage), $this->getNameWithTeam()." 额外伤害 -$damage");
        $this->decHP($damage, $source);
    }

    function decHP($hp, $source) {
        $this->notify(EventType::Hurt, $source);
        $this->_hp -= $hp;

        if ($this->_hp < 0) {
            $this->_hp = 0;
        }
    }

    public function restoreHP($hp) {
        $inc_hp = $this->incHP($hp);
        Record::add(RecordType::IncHP,  array('dest_sno' => $this->getSNO(), "hp" => $hp), $this->getNameWithTeam()." 回血 +$inc_hp");
    }

    function incHP($hp) {
        $inc_hp = $hp;
        $max_hp = self::$levels[$this->_id][$this->_level]["hp"];
        if ($this->_hp + $hp > $max_hp) {
            $inc_hp = $max_hp - $this->_hp;
        }
        $this->_hp += $inc_hp;
        return $inc_hp;
    }

    function compare_type($type) { // 卡牌类型比对
        if ($this->_type == $type) {
            return 0; // ==
        } else if (($this->_type) % 3 == $type - 1) {
            // (this->_type -1 + 1) % 3 == type - 1  类型最小值不是0 -1 是为了从0计数计算方便
            return 1; // >
        } else {
            return -1; // <
        }
    }

    function isDead() {
        return $this->_hp == 0;
    }
    protected $_has_crit = false; // 是否有暴击
    function getDamage($target_type, $target) {
        $prop = & self::$levels[$this->_id][$this->_level];
        $damage = mt_rand($prop['attack_min'], $prop['attack_max']);

        // 计算暴击
        if (FightHelper::IsHit($prop['crit'])) {
            Record::add(RecordType::Info,  array(), $this->getNameWithTeam()." 暴击 攻击力+".round($damage * $prop['crit_damage']));
            $damage += $damage * $prop['crit_damage'];
            $this->_has_crit = true;
        }

        // 计算精准度
        if (FightHelper::IsHit(0.3 - $prop['accuracy'])) {
            Record::add(RecordType::Info,  array(), $this->getNameWithTeam()." 攻击出现破绽，被防御 攻击力-".round($damage * 0.5));
            $damage -= $damage * 0.5;
        }

        // 卡牌属性克制有加成或减免
        $compare = $this->compare_type($target_type);
        if ($compare == 1) {
             $damage += $damage * 1.5;
        }

        if ($compare == -1) {
             $damage -= $damage * 0.2;
        }

        // 技能伤害
        $damage = $this->calcDamageByIncreaseAttackRates($damage);
        //Record::add(RecordType::Info,  array(), " damage :$damage");
        $damage = $target->calcDamageByDecreaseDamageRates($damage);
        //Record::add(RecordType::Info,  array(), " damage :$damage");

        // TODO 宝石伤害值 只有玩家的卡牌有
        return $damage;
    }

    function getType() {
        return $this->_type;
    }
    function getSkillID() {
        return $this->_skill_id;
    }
    function getName() {
        return $this->_name;
    }

    function getHP() {
        return $this->_hp;
    }
    function setHP($hp) {
        $this->_hp += $hp;
    }
    function getSNO() {
        return $this->_sno;
    }
    function setSNO($sno) {
        $this->_sno = $sno;
    }
};

class Monster extends Card
{
    function __construct($card_id) {
        $this->_level = mt_rand(1, 4);
        parent::__construct($card_id);
    }
}

class Soldier extends Card
{
    private $_gem_id = 0;
    private $_player_id = 0;
    function __construct($player_card) {
        $this->_level = $player_card['card_level'];
        $this->_player_card_id = $player_card['id'];
        parent::__construct($player_card['card_id']);
    }
}

class FightTeamType{
    const Player  = "Player";
    const Monster = "Monster";
};

class FightTeam
{
    private $_name = -1;
    private $_soldiers = array();
    private $_is_winner = false;

    function __construct($name, $soldiers) {
        $this->_name = $name;
        $this->_soldiers = $soldiers;
    }

    public function setWinner($sno) {
        $this->_is_winner = true;
    }

    public function getName() {
        return $this->_name;
    }

    public function getNext($sno) {
        if (++$sno >= count($this->_soldiers)) {
            return NULL;
        }
        return $this->_soldiers[$sno];
    }

    public function get($sno) {
        return $this->_soldiers[$sno];
    }

    public function setSNOs($name) {
        foreach($this->_soldiers as $i => $soldier) {
            $soldier->setSNO($name . "-" . $i);
        }
    }
}

class FightManager
{
    private $_teams = array(
        'A' => NULL,
        'B' => NULL,
    );
    private $_record = '';

    private $_fighter = array();
    private $_beating = NULL; // 打人
    private $_beaten  = NULL; // 挨打
    private $_count = 0;

    static private $_instance = NULL;
    private function __construct() {

    }
    function getInstance() {
        if (self::$_instance == NULL) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    function getSoldierParams($soldier) {
        return array(
            "dest_sno"     => $soldier->getSNO(),
            "card_id" => (int)$soldier->getID(),
            "hp"      => (int)$soldier->getHP(),
        );
    }

    function setTeams($team1, $team2)
    {
        // TODO 排队
        // A队先出招
        $this->_teams['A'] = $team1;
        $team1->setSNOs('A');

        $this->_teams['B'] = $team2;
        $team2->setSNOs('B');

        $this->_beating = $this->getSoldier("A-0"); // a 出招
        $this->_beaten  = $this->getSoldier("B-0"); // b 被打

        $beating_team_name = $this->getTeam($this->_beating->getSNO())->getName() . "的";
        $beating_name  = $beating_team_name . $this->_beating->getName();

        $beaten_team_name = $this->getTeam($this->_beaten->getSNO())->getName() . "的";
        $beaten_name  = $beaten_team_name . $this->_beaten->getName();


        Record::add(RecordType::Next, $this->getSoldierParams($this->_beating), $beating_name . " 上场！");
        Record::add(RecordType::Next, $this->getSoldierParams($this->_beaten), $beaten_name . " 上场！");
        // 出场事件
        FightHelper::Notify($this->_beating, $this->_beaten, EventType::PlayedStart);
        FightHelper::Notify($this->_beating, $this->_beaten, EventType::FightStart);
    }

    static function CreateSoldiers($player_cards) {
        $soldiers = array();
        foreach($player_cards as $player_card) {
           $soldiers[] = new Soldier($player_card);
        }
        return $soldiers;
    }

    static function RandMonsters($nums=3) {
        $card_ids = explode(",", $_REQUEST['card_ids']);
        $monsters = array();
        foreach($card_ids as $card_id) {
            // card_id 1~9
            $monsters[] = new Monster($card_id);
        }
        return $monsters;
    }

    public function getTeam($sno) {
        list($team, $i) = explode("-", $sno);
        return $this->_teams[$team];
    }

    public function setWinner($sno) {
        $team = $this->getTeam($sno);
        Record::$winner = $team->getName();
        Record::$count  = $this->_count;
        Record::add(RecordType::Winner, array('dest_sno' => $sno, ), $team->getName() . " 胜利.");
        return $team->setWinner();
    }

    public function getNextSoldier($sno) {
        list($team, $i) = explode("-", $sno);
        return $this->_teams[$team]->getNext(intval($i));
    }

    public function getSoldier($sno) {
        list($team, $i) = explode("-", $sno);
        return $this->_teams[$team]->get(intval($i));
    }

    public function fighting() {
        $this->_count++;
        Record::add(RecordType::RoundBegin, array(), "第 {$this->_count} 回合");
        FightHelper::Notify($this->_beating, $this->_beaten, EventType::RoundStart);
        for($i = 0; $i < 2; ++$i) {
            if ($this->attack()) {
                Record::add(RecordType::RoundEnd, array(), "第 {$this->_count} 回合结束");
                Record::add(RecordType::Ended, array(), "比赛结束.");
                return false;
            }
            $this->swap();
        }

        FightHelper::Notify($this->_beating, $this->_beaten, EventType::RoundEnd);
        Record::add(RecordType::RoundEnd, array(),"第 {$this->_count} 回合结束");
        return true;
    }

    public function swap() {
        $tmp            = $this->_beating;
        $this->_beating = $this->_beaten;
        $this->_beaten  = $tmp;
    }

    public function attack() {
        $demage = $this->_beating->attack($this->_beaten);
        if ($demage == 0) {
            return false;
        }
        $beating_name = $this->getTeam($this->_beating->getSNO())->getName(). "的" . $this->_beating->getName();
        $team_name = $this->getTeam($this->_beaten->getSNO())->getName() . "的";
        $beaten_name  = $team_name . $this->_beaten->getName();
        if ($this->_beaten->isDead()) {
            Record::add(RecordType::Killed, array('dest_sno' => $this->_beaten->getSNO(), 'card_id' => (int)$this->_beaten->getID() ), $beaten_name  . "阵亡！");
            $this->_beaten->notify(EventType::Death, $this->_beating); // 有复活技能
        }

        //
        if ($this->_beaten->isDead()) {

            $this->_beaten = $this->getNextSoldier($this->_beaten->getSNO());
            if ($this->_beaten == NULL) {
                $this->setWinner($this->_beating->getSNO());
                return true;
            }

            $beaten_name  = $team_name . $this->_beaten->getName();
            Record::add(RecordType::Next, $this->getSoldierParams($this->_beaten), $beaten_name . " 上场！");
            $this->_beaten->notify(EventType::PlayedStart, $this->_beating);
        }
        return false;
    }
}


?>
