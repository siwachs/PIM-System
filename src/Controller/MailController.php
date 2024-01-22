<?php

namespace App\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MailController extends FrontendController
{
    /**
     * @param Request $request
     * @return Response
     */
    public function mailerAction(Request $request): Response
    {
        return $this->render(
            'mail/email.html.twig'
        );
    }
}
