<?php

namespace App\Service;

use App\Entity\Contact;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class ContactNotificationService
{
    public function __construct(
        private Environment $twig,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $contactFromEmail,
        private string $contactFromName,
        private string $contactAdminName,
        private string $contactAdminEmail
    ) {
    }

    public function logContactCreatedNotifications(Contact $contact): void
    {
        $this->logger->info('[CONTACT NOTIFICATION - PROCESS STARTED]', [
            'contact_email' => $contact->getEmail(),
        ]);

        try {
            $templatePayload = $this->buildTemplatePayload($contact);
            $adminBody = $this->renderTemplate('emails/contact_admin_created.html.twig', $templatePayload);
            $userBody = $this->renderTemplate('emails/contact_user_created.html.twig', $templatePayload);

            $notifications = $this->buildNotifications($contact, $adminBody, $userBody);

            $failedRecipients = [];

            foreach ($notifications as $notification) {
                $this->logNotificationPayload($notification);

                $isSent = $this->sendNotificationEmail(
                    notification: $notification,
                    contactEmail: $contact->getEmail()
                );

                if (!$isSent) {
                    $failedRecipients[] = $notification->toEmail;
                }
            }

            $this->logProcessSummary($contact->getEmail(), $failedRecipients);
        } catch (\Throwable $exception) {
            $this->logger->error('[CONTACT NOTIFICATION - PROCESS FAILED]', [
                'contact_email' => $contact->getEmail(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{email: string, firstName: string, lastName: string}
     */
    private function buildTemplatePayload(Contact $contact): array
    {
        return [
            'email' => $contact->getEmail(),
            'firstName' => $contact->getFirstName(),
            'lastName' => $contact->getLastName(),
        ];
    }

    /**
     * @param array{email: string, firstName: string, lastName: string} $payload
     */
    private function renderTemplate(string $template, array $payload): string
    {
        return $this->twig->render($template, $payload);
    }

    /**
     * @return NotificationPayload[]
     */
    private function buildNotifications(Contact $contact, string $adminBody, string $userBody): array
    {
        return [
            $this->buildNotification(
                target: 'ADMIN',
                toEmail: $this->contactAdminEmail,
                toName: $this->contactAdminName,
                subject: 'Contact successfully created',
                body: $adminBody
            ),
            $this->buildNotification(
                target: 'USER',
                toEmail: $contact->getEmail(),
                toName: sprintf('%s %s', $contact->getFirstName(), $contact->getLastName()),
                subject: 'You were added as a contact',
                body: $userBody
            ),
        ];
    }

    private function buildNotification(
        string $target,
        string $toEmail,
        ?string $toName,
        string $subject,
        string $body
    ): NotificationPayload {
        return new NotificationPayload(
            target: $target,
            toEmail: $toEmail,
            toName: $toName,
            subject: $subject,
            body: $body
        );
    }

    private function logNotificationPayload(NotificationPayload $notification): void
    {
        $this->logger->info(sprintf('[CONTACT NOTIFICATION - %s]', $notification->target), [
            'to' => $notification->toEmail,
            'to_name' => $notification->toName,
            'subject' => $notification->subject,
            'body' => trim($notification->body),
        ]);
    }

    private function sendNotificationEmail(NotificationPayload $notification, string $contactEmail): bool
    {
        try {
            $this->mailer->send($this->buildEmailMessage($notification));

            $this->logger->info('[CONTACT NOTIFICATION - SEND SUCCEEDED]', [
                'target' => $notification->target,
                'to' => $notification->toEmail,
                'to_name' => $notification->toName,
                'subject' => $notification->subject,
                'contact_email' => $contactEmail,
            ]);

            return true;
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('[CONTACT NOTIFICATION - SEND FAILED]', [
                'target' => $notification->target,
                'to' => $notification->toEmail,
                'to_name' => $notification->toName,
                'subject' => $notification->subject,
                'contact_email' => $contactEmail,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function buildEmailMessage(NotificationPayload $notification): Email
    {
        return (new Email())
            ->from($this->createAddress($this->contactFromEmail, $this->contactFromName))
            ->to($this->createAddress($notification->toEmail, $notification->toName))
            ->subject($notification->subject)
            ->html($notification->body);
    }

    private function createAddress(string $email, ?string $name = null): Address
    {
        if ($name === null || trim($name) === '') {
            return new Address($email);
        }

        return new Address($email, $name);
    }

    /**
     * @param string[] $failedRecipients
     */
    private function logProcessSummary(string $contactEmail, array $failedRecipients): void
    {
        if ($failedRecipients === []) {
            $this->logger->info('[CONTACT NOTIFICATION - PROCESS COMPLETED]', [
                'contact_email' => $contactEmail,
            ]);

            return;
        }

        $this->logger->warning('[CONTACT NOTIFICATION - PROCESS PARTIAL FAILURE]', [
            'contact_email' => $contactEmail,
            'failed_recipients' => $failedRecipients,
        ]);
    }
}
