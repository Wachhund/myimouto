<?php

class UserMailer extends Rails\ActionMailer\Base
{
    protected function init()
    {
        $this->from = CONFIG()->email_from ?: CONFIG()->admin_contact;
    }

    public static function normalize_address($address)
    {
        return $address;
        // if defined?(IDN)
        // address =~ /\A([^@]+)@(.+)\Z/
        // mailbox = $1
        // domain = IDN::Idna.toASCII($2)
        // "#{mailbox}@#{domain}"
        // else
        // address
        // end
    }

    public function new_password($user, $password)
    {
        $recipients = self::normalize_address($user->email);
        $subject = CONFIG()->app_name . " - Password Reset";
        $this->user = $user;
        $this->password = $password;

        $this->to = $recipients;
        $this->subject = $subject;
    }

    public function password_reset($user, $reset_token)
    {
        $recipients = self::normalize_address($user->email);
        $subject = CONFIG()->app_name . " - Password Reset Request";
        $this->user = $user;
        $this->reset_url = CONFIG()->url_base . "/user/reset_password?token=" . urlencode($reset_token);

        $this->to = $recipients;
        $this->subject = $subject;
    }

    public function dmail($recipient, $sender, $msg_title, $msg_body)
    {
        $recipients = self::normalize_address($recipient->email);
        $subject = CONFIG()->app_name . " - Message received from " . $sender->name;
        $this->body = $msg_body;
        $this->sender = $sender;
        $this->subject = $msg_title;

        $this->to = $recipients;
        $this->subject = $subject;
    }
}
