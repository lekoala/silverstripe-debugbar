<?php

namespace LeKoala\DebugBar\Bridge\SymfonyMailer;

use Symfony\Component\Mime\Address;
use SilverStripe\Control\Email\Email;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;

/**
 * Collects data about sent mails
 */
class SymfonyMailerCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * @var Email[]
     */
    protected $emails;

    public function collect()
    {
        $mails = [];

        foreach ($this->emails as $msg) {
            $mails[] = [
                'to' => $this->formatTo($msg->getTo()),
                'subject' => $msg->getSubject(),
                'headers' => $msg->getHeaders()->toString()
            ];
        }

        return [
            'count' => count($mails),
            'mails' => $mails
        ];
    }

    public function add(Email $email)
    {
        $this->emails[] = $email;
    }

    /**
     * @param Address[] $to
     * @return string
     */
    protected function formatTo($to)
    {
        if (empty($to)) {
            return '';
        }

        $f = [];
        foreach ($to as $k => $v) {
            $f[] = $v->toString();
        }
        return implode(', ', $f);
    }

    public function getName()
    {
        return 'symfonymailer_mails';
    }

    public function getWidgets()
    {
        return [
            'emails' => [
                'icon' => 'inbox',
                'widget' => 'PhpDebugBar.Widgets.MailsWidget',
                'map' => 'symfonymailer_mails.mails',
                'default' => '[]',
                'title' => 'Mails'
            ],
            'emails:badge' => [
                'map' => 'symfonymailer_mails.count',
                'default' => 'null'
            ]
        ];
    }

    public function getAssets()
    {
        return [
            'css' => 'widgets/mails/widget.css',
            'js' => 'widgets/mails/widget.js'
        ];
    }
}
