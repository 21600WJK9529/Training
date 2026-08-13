<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Exception\DuplicateContactEmailException;
use App\Service\ContactService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/contacts', name: 'api_contact_')]
final class ContactApiController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ContactService $contactService, ValidatorInterface $validator, LoggerInterface $logger): JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true);

            if (!is_array($payload)) {
                return new JsonResponse([
                    'message' => 'Invalid JSON payload.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $contact = $this->mapContact($payload);
            $violations = $validator->validate($contact);

            if (count($violations) > 0) {
                return new JsonResponse([
                    'message' => 'Validation failed.',
                    'errors' => $this->formatValidationErrors($violations),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $contactService->create($contact);
        } catch (DuplicateContactEmailException) {
            return new JsonResponse([
                'message' => 'A contact with this email already exists.',
            ], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $logger->error('[CONTACT API - CREATE FAILED]', [
                'error' => $exception->getMessage(),
            ]);

            return new JsonResponse([
                'message' => 'Unable to create contact right now. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => 'Contact created successfully.',
            'data' => [
                'id' => $contact->getId()?->toRfc4122(),
                'firstName' => $contact->getFirstName(),
                'lastName' => $contact->getLastName(),
                'email' => $contact->getEmail(),
                'active' => $contact->isActive(),
                'createdAt' => $contact->getCreatedAt()?->format(DATE_ATOM),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapContact(array $payload): Contact
    {
        $contact = new Contact();
        $contact->setFirstName($this->readString($payload, 'firstName'));
        $contact->setLastName($this->readString($payload, 'lastName'));
        $contact->setEmail($this->readString($payload, 'email'));

        if (array_key_exists('active', $payload)) {
            $contact->setActive((bool) $payload['active']);
        }

        return $contact;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readString(array $payload, string $key): string
    {
        return isset($payload[$key]) && is_string($payload[$key]) ? trim($payload[$key]) : '';
    }

    /**
     * @param iterable<ConstraintViolationInterface> $violations
     * @return array<string, string>
     */
    private function formatValidationErrors(iterable $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();

            if ($property === '') {
                continue;
            }

            $errors[$property] = $violation->getMessage();
        }

        return $errors;
    }
}

