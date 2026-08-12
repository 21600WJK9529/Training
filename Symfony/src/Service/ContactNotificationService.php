<?php

namespace App\Service;

use App\Entity\Contact;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final readonly class ContactNotificationService
{
    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        private string $contactAdminEmail
    ) {
    }

    public function logContactCreatedNotifications(Contact $contact): void
    {
        $adminMessage = $this->twig->render('emails/contact_admin_created.html.twig', [
            'email' => $contact->getEmail(),
            'firstName' => $contact->getFirstName(),
            'lastName' => $contact->getLastName(),
        ]);

        $contactMessage = $this->twig->render('emails/contact_user_created.html.twig', [
            'email' => $contact->getEmail(),
            'firstName' => $contact->getFirstName(),
            'lastName' => $contact->getLastName(),
        ]);

        $this->logger->info('Dummy email to admin', [
            'to' => $this->contactAdminEmail,
            'subject' => 'Contact successfully created',
            'body' => $adminMessage,
        ]);

        $this->logger->info('Dummy email to contact', [
            'to' => $contact->getEmail(),
            'subject' => 'You were added as a contact',
            'body' => $contactMessage,
        ]);
    }
}
