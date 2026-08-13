<?php

namespace App\Service;

use App\Entity\Contact;
use App\Exception\DuplicateContactEmailException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContactService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactNotificationService $contactNotificationService
    ) {
    }

    public function create(Contact $contact): void
    {
        try {
            $this->entityManager->persist($contact);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateContactEmailException('Contact email already exists.', 0, $exception);
        }

        $this->contactNotificationService->logContactCreatedNotifications($contact);
    }

    /**
     * @return Contact[]
     */
    public function listAll(): array
    {
        return $this->entityManager
            ->getRepository(Contact::class)
            ->findBy([], ['createdAt' => 'DESC']);
    }
}
