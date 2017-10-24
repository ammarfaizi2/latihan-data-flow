<?php

namespace Bot\SaveEvent\PrivateChat;

use DB;
use Bot\Bot;
use Contracts\SaveEvent;

class Text implements SaveEvent
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

	/**
	 * Save event.
	 */
	public function save()
	{
		$query = "INSERT INTO `private_messages` (`msg_uniq`, `user_id`, `message_id`, `reply_to_message_id`, `type`, `created_at`) VALUES ";
		$query2 = "INSERT INTO `private_messages_data` (`msg_uniq`, `text`, `file`) VALUES ";
		$data  = $data2 = [];

		// prepare 300 data
		for ($i=0; $i < 300; $i++) { 
			$query .= "(:msg_uniq{$i}, :user_id{$i}, :message_id{$i}, :reply_to_message_id{$i}, :type{$i}, :created_at),";
			$data[":msg_uniq{$i}"] = $i.($uniq = $this->b->msgid."|".$this->b->chat_id);
			$data[":user_id{$i}"]  = $this->b->user_id;
			$data[":message_id{$i}"] = $this->b->msgid;
			$data[":reply_to_message_id{$i}"] = (isset($this->b->replyto['message_id']) ? $this->b->replyto['message_id'] : null);
			$data[":type{$i}"] = "text";
			$data[":created_at"] = date("Y-m-d H:i:s");

			$query2 .= "(:msg_uniq{$i}, :txt, NULL),";
			$data2[":msg_uniq{$i}"] = $i.$uniq;
		}
		$data2[":txt"] = $this->b->text;
		$st = DB::prepare(rtrim($query, ","));
		pc($st->execute($data), $st);

		$st = DB::prepare(rtrim($query2, ","));
		pc($st->execute($data2), $st);
	}
}