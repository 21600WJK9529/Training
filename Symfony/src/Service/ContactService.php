<?php

namespace App\Service;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContactService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function create(Contact $contact): void
    {
        $this->entityManager->persist($contact);
        $this->entityManager->flush();
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
