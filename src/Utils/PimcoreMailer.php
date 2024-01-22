<?php

namespace App\Utils;

use Pimcore\Controller\FrontendController;
use Pimcore\Mail;

/**
 * Class PimcoreMailer
 *
 * Utility class for sending emails using Pimcore's Mail service.
 */
class PimcoreMailer extends FrontendController
{
    /**
     * @var Mail The Pimcore Mail service instance.
     */
    private $pimcoreMailer;

    /**
     * PimcoreMailer constructor.
     *
     * @param Mail $pimcoreMailer The Pimcore Mail service instance.
     */
    public function __construct(Mail $pimcoreMailer)
    {
        $this->pimcoreMailer = $pimcoreMailer;
    }

    /**
     * Sends an email using Pimcore's Mail service.
     *
     * @param string $sender       The email sender.
     * @param string $receiver     The email receiver.
     * @param string $subject      The email subject.
     * @param string $message      The email message.
     * @param string $templatePath The path to the email template.
     *
     * @throws \Exception If an error occurs while sending the email.
     */
    public function sendMail(
        string $sender,
        string $receiver,
        string $subject,
        string $message,
        string $templatePath
    ): void {
        try {
            // Set email details and send
            $this->pimcoreMailer->to($receiver);
            $this->pimcoreMailer->subject($subject);
            $this->pimcoreMailer->setDocument($templatePath);
            $this->pimcoreMailer->send();
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }
}
