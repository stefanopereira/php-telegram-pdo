<?php

require_once 'TelegramBotCore.php';
include_once '/../JoomlaBotChat.php';


class TelegramBot extends TelegramBotCore {

    protected $chatClass;
    protected $chatInstances = array();

    public function __construct($token, $chat_class, $options = array()) {
        parent::__construct($token, $options);
        
        print_r($chat_class);
        
        
        $instance = new $chat_class($this, 0);
        if (!($instance instanceof TelegramBotChat)) {
            throw new Exception('ChatClass must be extends TelegramBotChat');
        }
        $this->chatClass = $chat_class;
    }

    public function onUpdateReceived($update) {
        if ($update['message']) {
            $message = $update['message'];
            $chat_id = intval($message['chat']['id']);
            if ($chat_id) {
                $chat = $this->getChatInstance($chat_id);
                if (isset($message['group_chat_created'])) {
                    $chat->bot_added_to_chat($message);
                } else if (isset($message['new_chat_participant'])) {
                    if ($message['new_chat_participant']['id'] == $this->botId) {
                        $chat->bot_added_to_chat($message);
                    }
                } else if (isset($message['left_chat_participant'])) {
                    if ($message['left_chat_participant']['id'] == $this->botId) {
                        $chat->bot_kicked_from_chat($message);
                    }
                } else {
                    $text = trim($message['text']);
                    $username = strtolower('@' . $this->botUsername);
                    $username_len = strlen($username);
                    if (strtolower(substr($text, 0, $username_len)) == $username) {
                        $text = trim(substr($text, $username_len));
                    }
                    if (preg_match('/^(?:\/([a-z0-9_]+)(@[a-z0-9_]+)?(?:\s+(.*))?)$/is', $text, $matches)) {
                        $command = $matches[1];
                        $command_owner = strtolower($matches[2]);
                        $command_params = $matches[3];
                        if (!$command_owner || $command_owner == $username) {
                            $method = 'command_' . $command;
                            if (method_exists($chat, $method)) {
                                $chat->$method($command_params, $message);
                            } else {
                                $chat->some_command($command, $command_params, $message);
                            }
                        }
                    } else {
                        $chat->message($text, $message);
                    }
                }
            }
        }
    }

    protected function getChatInstance($chat_id) {
        if (!isset($this->chatInstances[$chat_id])) {
            $instance = new $this->chatClass($this, $chat_id);
            $this->chatInstances[$chat_id] = $instance;
            $instance->init();
        }
        return $this->chatInstances[$chat_id];
    }

}
