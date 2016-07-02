<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Decription: Space for command and procces definitions
 *
 * @author Gerley Adriano
 */
require_once 'telegram/TelegramBotChat.php';

class MyBotChat extends TelegramBotChat {

    public function command_start($params, $message) {
        $this->apiSendMessage('Este bot está funcionado');
    }

    public function message($text, $message) {
        $notification = array(0 => "This bot is in development stage.",
            1 => "I'm sorry, the bot not's finished!",
            2 => "Don't have again! ...Please");

        $this->apiSendMessage($notification[rand(0, 1, 2)]);
    }

}
