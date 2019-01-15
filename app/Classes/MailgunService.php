<?php

namespace App\Classes;

use App\Models\Campaign;
use App\Models\Drop;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Mailgun\Mailgun;

class MailgunService
{
    /**
     * Sending Domain
     * TODO: refactor this into campaign settings/setup
     * @var string
     */
    public $domain = '';

    /**
     * Mailgun API Client
     * @var Mailgun
     */
    protected $mailgun;

    /**
     * Mailgun API Key
     * @var string
     */
    private $key = '';

    /**
     * MailgunService constructor.
     */
    public function __construct()
    {
        /* The old way
        $config = new GuzzleClient(['verify' => 'false']);

        $adapter = new GuzzleAdapter($config);

        $this->mailgun = new Mailgun($this->key, $adapter);
        */

        $this->domain = env('MAILGUN_DOMAIN');
        $this->key = env('MAILGUN_KEY');
        $this->mailgun = Mailgun::create($this->key);
    }


    /**
     * Send an email through the Mailgun Service
     * TODO: Decouple method from parameter object types
     *
     * @param Campaign       $campaign
     * @param Recipient      $recipient
     * @param                $subject
     * @param                $html
     * @param                $text
     *
     * @return bool
     */
    public function sendEmail(Campaign $campaign, Recipient $recipient, $subject, $html, $text)
    {
        try {
            echo "sending email to " . $recipient->email . "\n";

            return $this->mailgun->messages()->send($this->domain, [
                'from'    => $this->getFromLine($campaign, $recipient, $campaign->client),
                'to'      => $recipient->email,
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text,
                'o:tag'   => ['profitminer_campaign_' . $campaign->id],
            ]);
        } catch (\Exception $e) {
            Log::error("Unable to send email: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send an email through the Mailgun Service
     * TODO: Decouple method from parameter object types
     *
     * @param Campaign       $campaign
     * @param                $subject
     * @param                $xml
     *
     * @return bool
     * @throws \Exception
     */
    public function sendXmlEmail(Campaign $campaign, $subject, $xml)
    {
        if (!filter_var($campaign->adf_crm_export_email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid CRM Email address. Quitting.');
        }

        try {
            echo "sending xml email to " . $campaign->adf_crm_export_email . "\n";

            return $this->mailgun->messages()->send($this->domain, [
                'from'    => 'Profit Miner <no-reply@' . $this->domain . '>',
                'to'      => $campaign->adf_crm_export_email,
                'subject' => $subject,
                'text'    => $text,
                'html'    => null,
            ]);
        } catch (\Exception $e) {
            Log::error("Unable to send email: " . $e->getMessage());

            return false;
        } catch (\Exception $e) {
        }
    }

    /**
     * Relay the inbound message to the client's pass-through contact
     *
     * @param Campaign               $campaign
     * @param Recipient              $recipient
     * @param                        $subject
     * @param                        $html
     * @param                        $text
     *
     * @return \Mailgun\Model\Message\SendResponse
     * @internal param \Illuminate\Http\Request $request
     */
    public function sendClientEmail(Campaign $campaign, Recipient $recipient, $subject, $html, $text)
    {
        $response = $this->mailgun->messages()->send('mg.automotive-alerts.com', [
            'from'    => $this->getFromLine($campaign, $recipient, $campaign->client),
            'to'      => $recipient->email,
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ]);

        return $response;
    }

    /**
     * Relay the inbound message to the client's pass-through contact
     *
     * @param Campaign       $campaign
     * @param Recipient      $recipient
     * @param                $subject
     * @param                $html
     * @param                $text
     *
     * @return void
     * @internal param \Illuminate\Http\Request $request
     */
    public function sendPassthroughEmail(Campaign $campaign, Recipient $recipient, $subject, $html, $text)
    {
        $emails = explode(',', $campaign->client_passthrough_email);
        $from = $this->getFromLine($campaign, $recipient, $campaign->client);

        foreach ($emails as $email) {
            $this->mailgun->messages()->send('mg.automotive-alerts.com', [
                'from'    => $from,
                'to'      => trim($email),
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text,
            ]);

        }

    }

    /**
     * Extract email message properties from objects
     *
     *
     * @param Drop      $drop
     * @param Recipient $recipient
     * @return array
     */
    public function getMessage(Drop $drop, Recipient $recipient)
    {
        return [
            'from'    => $this->getFromLine($drop->campaign, $recipient, $drop->campaign->client),
            'to'      => $recipient->email,
            'subject' => $drop->subject,
            'html'    => $drop->email_html,
            'text'    => $drop->email_text,
        ];
    }

    /**
     * The Email "from" address
     *
     *
     * @param Campaign  $campaign
     * @param Recipient $recipient
     * @param User      $client
     * @return string
     */
    public function getFromLine(Campaign $campaign, Recipient $recipient, User $client)
    {
        $from = $client->organization ?: "{$client->first_name} {$client->last_name}";

        return "{$from} <" . str_slug("{$from}") . "_{$campaign->id}_{$recipient->id}@{$this->domain}>";
    }


    /**
     * Validate emails against Mailgun
     *
     * @param string $email
     * @return mixed
     * @throws \Exception
     */
    public function validateEmail($email = '')
    {
        try {
            $response = $this->mailgun->validator()->validate($email);

            return $response->mailbox_verification == 'true';
        } catch (\Exception $e) {
            throw new \Exception("Unable to validate emails. " . $e->getMessage());
        }
    }
}
