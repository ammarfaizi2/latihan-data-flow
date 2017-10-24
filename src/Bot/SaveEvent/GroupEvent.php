<?php

namespace Bot\SaveEvent;

use DB;
use PDO;
use Bot\Bot;
use Telegram as B;
use Bot\Abstraction\EventFoundation;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com>
 * @license MIT
 */
class GroupEvent extends EventFoundation
{	

	/**
	 * @var \Bot\Bot
	 */
	private $b;

	/**
	 * Constructor.
	 *
	 * @param \Bot\Bot $bot
	 */
	public function __construct(Bot $bot)
	{
		$this->b = $bot;
	}

	private function trackEvent()
	{
		$st = DB::prepare("SELECT `username`,`name`,`private_link`,`photo` FROM `a_groups` WHERE `group_id`=:group_id LIMIT 1;");
		pc($st->execute([":group_id" => $this->b->chat_id]), $st);
		$st = $st->fetch(PDO::FETCH_ASSOC);
		if (! $st) {
			return false;
		}

		if (
			$st['username'] !== $this->b->chatuname ||
			$st['name']		!== $this->b->chattitle
		) {
			return "update";
		}
		return "known";
	}

	private function updateGroupInfo()
	{
		$st = DB::prepare("UPDATE `a_groups` SET `username`=:username, `name`=:name, `private_link`=NULL, `updated_at`=:updated_at, `msg_count`=`msg_count`+1 WHERE `group_id`=:group_id LIMIT 1;");
		pc($st->execute(
			[
				":username" 	=> $this->b->username,
				":name"			=> $this->b->name,
				":updated_at"	=> date("Y-m-d H:i:s"),
				":group_id"		=> $this->b->chat_id
			]
		), $st);
		$this->writeGroupHistory();
		return true;
	}

	private function increaseMessageCount()
	{
		$st = DB::prepare("UPDATE `a_groups` SET `msg_count`=`msg_count`+1 WHERE `group_id`=:group_id LIMIT 1;");
		pc($st->execute([":group_id" => $this->b->chat_id]), $st);
		return true;
	}

	private function saveNewGroup()
	{
		$st = DB::prepare("INSERT INTO `a_groups` (`group_id`, `username`, `name`, `private_link`, `photo`, `msg_count`, `created_at`, `updated_at`) VALUES (:group_id, :username, :name, NULL, NULL, 1, :created_at, NULL);");
		pc($st->execute(
			[
				":group_id" 	=> $this->b->chat_id,
				":name"			=> $this->b->chattitle,
				":username"		=> $this->b->chatuname,
				":created_at"	=> date("Y-m-d H:i:s")
			]
		), $st);
		$st = DB::prepare("INSERT INTO `groups_setting` (`group_id`, `max_warn`, `welcome_message`) VALUES (:group_id, 3, NULL)");
		pc($st->execute([":group_id" => $this->b->chat_id]), $st);
		$this->writeGroupHistory();
		return true;
	}

	public function flushGroupAdmin()
	{
		$query = "INSERT INTO `group_admins` (`group_id`,`user_id`,`status`,`privileges`,`created_at`,`updated_at`) VALUES ";
		$st = json_decode(B::getChatAdministrators(
			[
				"chat_id" => $this->b->chat_id
			]
		)['content'], true) xor $admin = [];
		var_dump($st);
		if (isset($st['result'])) {
			$i = 1;
			$admin[":group_id"] = $this->b->chat_id;
			$admin[":created_at"] = date("Y-m-d H:i:s");
			$admin[':updated_at'] = date("Y-m-d H:i:s");
			foreach ($st['result'] as $val) {
				$admin[":user_id_{$i}"] = $val['user']['id'];
				$st = DB::prepare("INSERT INTO `a_users` (`user_id`, `username`, `name`, `photo`, `private_msg_count`, `group_msg_count`, `created_at`, `updated_at`) VALUES (:user_id, :username, :name, NULL, 0, 0, :created_at, NULL);");
				if ($st->execute(
					[
						":user_id" 		=> $val['user']['id'],
						":username" 	=> (isset($val['user']['username']) ? $val['user']['username'] : ""),
						":name"			=> $val['user']['first_name'] . (isset($val['user']['last_name']) ? " ".$val['user']['last_name'] : ""),
						":created_at"	=> date("Y-m-d H:i:s")
					]
				)) {
					$st = DB::prepare("INSERT INTO `users_history` (`user_id`, `username`, `name`, `photo`, `created_at`) VALUES (:user_id, :username, :name, NULL, :created_at)");
					pc($st->execute(
						[
							":user_id" 		=> $val['user']['id'],
							":username" 	=> (isset($val['user']['username']) ? $val['user']['username'] : ""),
							":name"			=> $val['user']['first_name'] . (isset($val['user']['last_name']) ? " ".$val['user']['last_name'] : ""),
							":created_at"	=> date("Y-m-d H:i:s")
						]
					), $st);
				}
				$admin[":status_{$i}"] = $val['status'];
				unset($val['user'], $val['status']);
				$admin[":privileges_{$i}"] = $admin[":status_{$i}"]==="creator" ? "all" : json_encode($val);
				$query .= "(:group_id, :user_id_{$i}, :status_{$i}, :privileges_{$i}, :created_at, :updated_at),";
				$i++;
			}
			$st = DB::prepare("DELETE FROM `group_admins` WHERE `group_id`=:group_id;");
			pc($st->execute([":group_id" => $this->b->chat_id]), $st);
			$st = DB::prepare(rtrim($query, ",").";");
			pc($st->execute($admin), $st);
		}
	}

	private function writeGroupHistory()
	{
		$st = DB::prepare("INSERT INTO `groups_history` (`group_id`, `username`, `name`, `created_at`) VALUES (:group_id, :username, :name, :created_at);");
		pc($st->execute(
			[
				":group_id" 	=> $this->b->chat_id,
				":username"		=> $this->b->chatuname,
				":name"			=> $this->b->chattitle,
				":created_at"	=> date("Y-m-d H:i:s")
			]
		), $st);
		return true;
	}

	public function run()
	{
		$track = $this->trackEvent();
		if ($track === "update") {
			$this->updateGroupInfo();
			$this->flushGroupAdmin();
		} elseif ($track === "known") {
			$this->increaseMessageCount();
			$get = 0;
			is_dir(STORAGE."/groups") or mkdir(STORAGE."/groups");
			if (file_exists($a = STORAGE."/groups/".$this->b->chat_id."_flush_privileges")) {
				$get = (int)file_get_contents($a);
				if ($get === 10) {
					$this->flushGroupAdmin();
					file_put_contents($a, 0);
				} else {
					file_put_contents($a, ++$get);
				}
			} else {
				file_put_contents($a, ++$get);
			}
		} else {
			$this->saveNewGroup();
			$this->flushGroupAdmin();
		}
	}
}