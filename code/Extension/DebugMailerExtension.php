<?php

namespace LeKoala\DebugBar\Extension;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Core\Extension;
use SilverStripe\Control\Email\Email;
use Symfony\Component\Mailer\Event\MessageEvent;

/**
 * Extends \SilverStripe\Control\Email\MailerSubscriber
 */
class DebugMailerExtension extends Extension
{
    /**
     * Store queries
     *
     * @var array
     */
    protected static $queries = [];

    /**
     * @param Email $email
     * @param MessageEvent $event
     */
    function updateOnMessage($email, $event)
    {
        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) use ($email) {
            /** @var \LeKoala\DebugBar\Bridge\SymfonyMailer\SymfonyMailerCollector $mailerCollector */
            $mailerCollector = $debugbar->getCollector('symfonymailer_mails');
            $mailerCollector->add($email);
        });
    }
}
