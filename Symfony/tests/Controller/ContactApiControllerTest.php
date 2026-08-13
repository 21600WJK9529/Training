<?php

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->resetDatabase();
    }

    #[TestDox('POST /api/contacts returns 201 and persists contact data when bearer token and payload are valid')]
    public function testCreateContactReturnsCreatedForValidPayload(): void
    {
        $token = $this->createApiToken();
        $email = sprintf('api-user-%s@example.com', uniqid('', true));

        $this->client->request(
            'POST',
            '/api/contacts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
            content: (string) json_encode([
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'email' => $email,
                'active' => true,
            ])
        );

        $responseBody = (string) $this->client->getResponse()->getContent();
        self::assertResponseStatusCodeSame(201, sprintf('Expected 201 for valid payload, got response: %s', $responseBody));

        $responseData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $context = $this->responseContext($responseData);

        self::assertArrayHasKey('message', $responseData, sprintf('Missing message key. %s', $context));
        self::assertArrayHasKey('data', $responseData, sprintf('Missing data key. %s', $context));
        self::assertIsArray($responseData['data'], sprintf('Expected data to be an array. %s', $context));

        self::assertSame('Contact created successfully.', $responseData['message'] ?? null, sprintf('Unexpected success message. %s', $context));
        self::assertSame('Jane', $responseData['data']['firstName'] ?? null, sprintf('Expected firstName to match request payload. %s', $context));
        self::assertSame('Doe', $responseData['data']['lastName'] ?? null, sprintf('Expected lastName to match request payload. %s', $context));
        self::assertSame($email, $responseData['data']['email'] ?? null, sprintf('Expected email to match request payload. %s', $context));
        self::assertTrue($responseData['data']['active'] ?? false, sprintf('Expected active=true for valid payload. %s', $context));
        self::assertNotEmpty($responseData['data']['id'] ?? null, sprintf('Expected created contact id to be present. %s', $context));
        self::assertNotEmpty($responseData['data']['createdAt'] ?? null, sprintf('Expected createdAt timestamp to be present. %s', $context));
    }

    #[TestDox('POST /api/contacts returns 422 with field-level validation errors when firstName/lastName are blank and email format is invalid')]
    public function testCreateContactReturnsValidationErrorsForInvalidPayload(): void
    {
        $token = $this->createApiToken();

        $this->client->request(
            'POST',
            '/api/contacts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
            content: (string) json_encode([
                'firstName' => '',
                'lastName' => '',
                'email' => 'invalid-email',
                'active' => true,
            ])
        );

        $responseBody = (string) $this->client->getResponse()->getContent();
        self::assertResponseStatusCodeSame(422, sprintf('Expected 422 for invalid payload, got response: %s', $responseBody));

        $responseData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $context = $this->responseContext($responseData);

        self::assertSame('Validation failed.', $responseData['message'] ?? null, sprintf('Unexpected validation summary message. %s', $context));
        self::assertArrayHasKey('errors', $responseData, sprintf('Missing errors object in validation response. %s', $context));
        self::assertIsArray($responseData['errors'], sprintf('Expected errors to be an array. %s', $context));
        self::assertCount(3, $responseData['errors'], sprintf('Expected exactly 3 validation errors for firstName, lastName and email. %s', $context));

        self::assertArrayHasKey('firstName', $responseData['errors'], sprintf('Missing firstName validation error. %s', $context));
        self::assertArrayHasKey('lastName', $responseData['errors'], sprintf('Missing lastName validation error. %s', $context));
        self::assertArrayHasKey('email', $responseData['errors'], sprintf('Missing email validation error. %s', $context));

        self::assertSame('This value should not be blank.', $responseData['errors']['firstName'], sprintf('Expected firstName to fail NotBlank. %s', $context));
        self::assertSame('This value should not be blank.', $responseData['errors']['lastName'], sprintf('Expected lastName to fail NotBlank. %s', $context));
        self::assertSame('Please enter a valid email address.', $responseData['errors']['email'], sprintf('Expected email to fail email format validation. %s', $context));
    }

    #[TestDox('POST /api/contacts returns 401 with a detailed auth reason when bearer token format is invalid')]
    public function testCreateContactReturnsUnauthorizedForInvalidBearerToken(): void
    {
        $this->client->request(
            'POST',
            '/api/contacts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token-value',
            ],
            content: (string) json_encode([
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'email' => sprintf('invalid-bearer-%s@example.com', uniqid('', true)),
                'active' => true,
            ])
        );

        $responseBody = (string) $this->client->getResponse()->getContent();
        self::assertResponseStatusCodeSame(401, sprintf('Expected 401 for invalid bearer token, got response: %s', $responseBody));

        $responseData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $context = $this->responseContext($responseData);
        self::assertSame('Token format is invalid.', $responseData['message'] ?? null, sprintf('Expected detailed invalid token reason. %s', $context));
    }

    private function createApiToken(): string
    {
        $username = (string) ($_SERVER['API_AUTH_USERNAME'] ?? $_ENV['API_AUTH_USERNAME'] ?? '');
        $password = (string) ($_SERVER['API_AUTH_PASSWORD'] ?? $_ENV['API_AUTH_PASSWORD'] ?? '');

        self::assertNotSame('', $username, 'API_AUTH_USERNAME must be configured for tests.');
        self::assertNotSame('', $password, 'API_AUTH_PASSWORD must be configured for tests.');

        $this->client->request(
            'POST',
            '/api/auth/token',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: (string) json_encode([
                'username' => $username,
                'password' => $password,
            ])
        );

        $responseBody = (string) $this->client->getResponse()->getContent();
        self::assertResponseStatusCodeSame(200, sprintf('Expected token request to succeed. Response: %s', $responseBody));

        $responseData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $context = $this->responseContext($responseData);
        self::assertArrayHasKey('token', $responseData, sprintf('Token response must include a token field. %s', $context));
        self::assertIsString($responseData['token'], sprintf('Token field must be a string. %s', $context));
        self::assertNotSame('', trim($responseData['token']), sprintf('Token must not be empty. %s', $context));

        return $responseData['token'];
    }

    private function resetDatabase(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ($metadata === []) {
            return;
        }

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * @param array<string, mixed> $responseData
     */
    private function responseContext(array $responseData): string
    {
        return sprintf('Response payload: %s', json_encode($responseData, JSON_THROW_ON_ERROR));
    }
}


